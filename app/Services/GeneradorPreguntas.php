<?php

namespace App\Services;

use App\Models\Pregunta;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class GeneradorPreguntas
{
    /** Últimos temas usados en la partida (los borra "Reiniciar partida"). */
    public const CLAVE_TEMAS_RECIENTES = 'trivia.temas_recientes';

    /** Código de la excepción cuando Gemini responde 429 (cuota agotada). */
    public const CUOTA_AGOTADA = 429;

    /** Cuántos temas recientes se evitan repetir. */
    private const NO_REPETIR = 5;

    /** Timeouts (segundos): una pregunta suelta y un lote. */
    private const TIMEOUT = 45;
    private const TIMEOUT_LOTE = 90;

    /**
     * Tokens máximos de salida de un lote. El modelo acepta hasta 65536; 10 preguntas
     * ocupan ~3000 más el razonamiento (que también cuenta), así que 16384 deja margen.
     */
    private const MAX_TOKENS_LOTE = 16384;

    /** Esperas (segundos) antes de cada reintento cuando Gemini responde 503 (saturado). */
    private const ESPERAS_503 = [5, 15, 30];

    /**
     * Si está seteado (lo hace el comando con conReintentos()), se reintenta ante un 503
     * y un lote cortado se vuelve a pedir partido en dos. Recibe los mensajes de progreso.
     * En las rutas web queda en null: no hay tiempo para esperar.
     */
    private ?Closure $aviso = null;

    /** Activa los reintentos (503 y lotes cortados) y recibe los avisos para mostrarlos. */
    public function conReintentos(callable $aviso): static
    {
        $this->aviso = Closure::fromCallable($aviso);

        return $this;
    }

    /** Reglas de calidad que comparten la pregunta suelta y el lote. */
    private const REGLAS = <<<'REGLAS'
- Pregunta conceptual, o del tipo "¿qué hace / qué devuelve este código?".
  Si incluís código, que sea un fragmento de máximo 2 líneas dentro del texto de la pregunta.
- Nada de fechas, números de versión ni nombres de creadores o empresas.
- Exactamente 4 opciones plausibles, de largo parecido entre sí (máximo 60 caracteres cada una).
- Una sola opción correcta.
- La explicación, de 1 o 2 oraciones.
REGLAS;

    /**
     * Genera UNA pregunta en el momento (respaldo cuando el banco está vacío).
     * Si Gemini falla, lanza RuntimeException con el código HTTP (429 = cuota agotada).
     */
    public function generar(array $yaHechas = []): array
    {
        $evitar = $this->textoEvitar($yaHechas);

        // Si no hay temas configurados, se usa el prompt general de siempre
        $tema = $this->elegirTema();
        $prompt = $tema === null ? $this->promptGeneral($evitar) : $this->promptConTema($tema, $evitar);

        $texto = $this->pedir($prompt, self::TIMEOUT)['texto'];
        $pregunta = json_decode($texto, true);

        if (! $this->esValida($pregunta)) {
            throw new RuntimeException('La IA devolvió un formato inválido: ' . $texto);
        }

        if ($tema !== null) {
            // El tema lo pone el servidor (no lo que diga la IA) y se anota como reciente
            // recién acá, cuando la pregunta salió bien
            $pregunta['tema'] = $tema;
            $this->recordarTema($tema);
        }

        return $pregunta;
    }

    /**
     * Genera $cantidad preguntas en UN SOLO pedido a Gemini y guarda en el banco las válidas
     * que no estén repetidas. Devuelve cuántas se guardaron.
     * Si Gemini falla, lanza RuntimeException con el código HTTP (429 = cuota agotada).
     *
     * @param  bool  $puedePartir  si la respuesta se corta por longitud, reintentar partido en dos
     *                            (solo una vez: las mitades ya no se vuelven a partir)
     */
    public function generarLote(int $cantidad = 10, bool $puedePartir = true): int
    {
        $cantidad = max(1, min($cantidad, 20));

        // Temas distintos elegidos de antemano: una pregunta por tema
        $temas = $this->elegirTemasDistintos($cantidad);

        // Las últimas 50 del banco, para que la IA no las repita
        $evitar = $this->textoEvitar(
            Pregunta::query()->latest('id')->limit(50)->pluck('texto')->all()
        );

        $respuesta = $this->pedir($this->promptLote($cantidad, $temas, $evitar), self::TIMEOUT_LOTE, self::MAX_TOKENS_LOTE);
        $texto = $respuesta['texto'];
        $json = json_decode($texto, true);

        // Aceptamos {"preguntas": [...]} o directamente [...]
        $lista = is_array($json) ? ($json['preguntas'] ?? (array_is_list($json) ? $json : null)) : null;

        // JSON cortado o roto: rescatamos las preguntas completas que llegaron antes del corte
        if (! is_array($lista)) {
            $lista = $this->rescatarPreguntas($texto);

            if ($lista !== []) {
                $this->avisar('JSON incompleto: se rescataron ' . count($lista) . ' preguntas completas antes del corte');
            }
        }

        $guardadas = $this->guardarLista($lista, $temas);

        // Se cortó por longitud: lo que faltó se vuelve a pedir partido en dos lotes más chicos
        if ($respuesta['cortada'] && $puedePartir && $this->aviso) {
            $faltan = $cantidad - count($lista);

            if ($faltan > 0) {
                $primera = (int) ceil($faltan / 2);
                $segunda = $faltan - $primera;
                $this->avisar('Respuesta cortada por longitud: se reintenta lo que faltó en dos lotes de '
                    . $primera . ($segunda !== $primera ? " y {$segunda}" : ''));

                $guardadas += $this->generarLote($primera, false);
                if ($segunda > 0) {
                    $guardadas += $this->generarLote($segunda, false);
                }
            }

            return $guardadas;
        }

        if ($lista === []) {
            throw new RuntimeException('La IA devolvió un formato inválido para el lote' . ($respuesta['cortada'] ? ' (respuesta cortada por longitud)' : ''));
        }

        return $guardadas;
    }

    /**
     * Valida y guarda cada pregunta por separado (una mala no tira abajo el lote).
     * El tema lo pone el servidor según la posición. Devuelve cuántas se guardaron.
     */
    private function guardarLista(array $lista, array $temas): int
    {
        $guardadas = 0;

        // Cada pregunta se valida por separado: una mala no tira abajo el lote
        foreach (array_values($lista) as $i => $pregunta) {
            if (! $this->esValida($pregunta)) {
                continue;
            }

            $pregunta['tema'] = $temas[$i] ?? null; // el tema lo pone el servidor, según el orden

            if (Pregunta::guardarSiNoExiste($pregunta)) {
                $guardadas++;
            }
        }

        return $guardadas;
    }

    // ------------------------------------------------------------------
    // Llamada a Gemini
    // ------------------------------------------------------------------

    /**
     * Manda el prompt a Gemini. Devuelve el texto y si la respuesta se cortó por longitud.
     * Usa razonamiento bajo en los modelos Gemini 3. Con conReintentos(), ante un 503
     * (Gemini saturado) reintenta hasta 3 veces esperando 5, 15 y 30 s. Nunca reintenta
     * un 429 (cuota agotada: no se arregla esperando unos segundos) ni un 400 (pedido mal armado).
     *
     * @return array{texto: string, cortada: bool}
     */
    private function pedir(string $prompt, int $timeout, ?int $maxTokens = null): array
    {
        $modelo = (string) config('services.gemini.model');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelo . ':generateContent';

        $generationConfig = [
            'responseMimeType' => 'application/json',
            'temperature' => 1.0,
        ];

        if ($maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }

        // Razonamiento bajo: el modelo "piensa" menos y responde mucho más rápido.
        // thinkingLevel solo existe en los modelos Gemini 3 (los 2.x usan thinkingBudget),
        // así que a los demás no se lo mandamos.
        if (str_starts_with($modelo, 'gemini-3')) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }

        $reintentos = $this->aviso ? self::ESPERAS_503 : [];

        for ($intento = 0; ; $intento++) {
            $respuesta = $this->llamarIa($url, $prompt, $generationConfig, $timeout);

            // Si el modelo rechaza la configuración de razonamiento (400), reintentamos sin ella
            if ($respuesta->status() === 400 && isset($generationConfig['thinkingConfig'])
                && str_contains(strtolower($respuesta->body()), 'thinking')) {
                unset($generationConfig['thinkingConfig']);
                $respuesta = $this->llamarIa($url, $prompt, $generationConfig, $timeout);
            }

            if ($respuesta->status() !== 503 || ! isset($reintentos[$intento])) {
                break;
            }

            $espera = $reintentos[$intento];
            $this->avisar('Gemini saturado, reintento ' . ($intento + 1) . '/' . count($reintentos) . " en {$espera} s...");
            Sleep::for($espera)->seconds();
        }

        if ($respuesta->failed()) {
            // El código de la excepción es el HTTP: así se distingue el 429 (cuota agotada)
            throw new RuntimeException($this->resumirError($respuesta), $respuesta->status());
        }

        return [
            'texto' => (string) $respuesta->json('candidates.0.content.parts.0.text'),
            // MAX_TOKENS = se alcanzó el límite de tokens de salida y la respuesta quedó incompleta
            'cortada' => $respuesta->json('candidates.0.finishReason') === 'MAX_TOKENS',
        ];
    }

    /** "Gemini 503: The model is overloaded..." en una sola línea (sin el JSON completo). */
    private function resumirError(Response $respuesta): string
    {
        $mensaje = $respuesta->json('error.message') ?? mb_substr($respuesta->body(), 0, 200);

        return 'Gemini ' . $respuesta->status() . ': ' . trim((string) preg_replace('/\s+/', ' ', (string) $mensaje));
    }

    private function avisar(string $mensaje): void
    {
        if ($this->aviso) {
            ($this->aviso)($mensaje);
        }
    }

    /**
     * Rescata de un JSON cortado los objetos {...} completos que hay dentro del primer array.
     * Recorre el texto contando llaves (ignorando las que están dentro de strings) y decodifica
     * cada objeto que llegó a cerrarse. El último, cortado a la mitad, se descarta.
     *
     * @return array<int, mixed>
     */
    private function rescatarPreguntas(string $texto): array
    {
        $inicio = strpos($texto, '[');

        if ($inicio === false) {
            return [];
        }

        $objetos = [];
        $nivel = 0;
        $desde = null;
        $enString = false;
        $escape = false;

        // Recorremos byte a byte: los caracteres que importan ({ } [ ] " \) son ASCII,
        // así que los acentos y emojis (UTF-8 multibyte) no molestan
        for ($i = $inicio + 1, $largo = strlen($texto); $i < $largo; $i++) {
            $c = $texto[$i];

            if ($enString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $enString = false;
                }

                continue;
            }

            if ($c === '"') {
                $enString = true;
            } elseif ($c === '{') {
                if ($nivel === 0) {
                    $desde = $i;
                }
                $nivel++;
            } elseif ($c === '}') {
                $nivel--;
                if ($nivel === 0 && $desde !== null) {
                    $objetos[] = json_decode(substr($texto, $desde, $i - $desde + 1), true);
                    $desde = null;
                }
            } elseif ($c === ']' && $nivel === 0) {
                break; // terminó el array de preguntas
            }
        }

        return $objetos;
    }

    private function llamarIa(string $url, string $prompt, array $generationConfig, int $timeout): Response
    {
        return Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->timeout($timeout)
            ->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $generationConfig,
            ]);
    }

    /** Valida una pregunta devuelta por la IA. */
    private function esValida(mixed $pregunta): bool
    {
        if (! is_array($pregunta) || ! is_string($pregunta['texto'] ?? null) || trim($pregunta['texto']) === '') {
            return false;
        }

        $opciones = $pregunta['opciones'] ?? null;

        if (! is_array($opciones) || count($opciones) !== 4) {
            return false;
        }

        foreach ($opciones as $opcion) {
            if (! is_string($opcion) || trim($opcion) === '') {
                return false;
            }
        }

        return in_array($pregunta['correcta'] ?? null, [0, 1, 2, 3], true)
            && is_string($pregunta['explicacion'] ?? '');
    }

    // ------------------------------------------------------------------
    // Temas
    // ------------------------------------------------------------------

    /** @return array<int, string> temas activos de config('trivia.temas') */
    private function temasConfigurados(): array
    {
        return array_values(array_filter((array) config('trivia.temas', [])));
    }

    /**
     * Elige un tema al azar de config('trivia.temas') evitando los últimos usados.
     * Devuelve null si no hay temas configurados.
     */
    private function elegirTema(): ?string
    {
        $temas = $this->temasConfigurados();

        if ($temas === []) {
            return null;
        }

        $recientes = Cache::get(self::CLAVE_TEMAS_RECIENTES, []);
        $disponibles = array_values(array_diff($temas, $recientes));

        // Si hay pocos temas y están todos "recientes", se elige entre todos
        return Arr::random($disponibles ?: $temas);
    }

    /**
     * $cantidad temas distintos al azar. Si hay menos temas que preguntas pedidas,
     * se repiten (mezclados de nuevo). Lista vacía si no hay temas configurados.
     *
     * @return array<int, string>
     */
    private function elegirTemasDistintos(int $cantidad): array
    {
        $temas = $this->temasConfigurados();

        if ($temas === []) {
            return [];
        }

        $elegidos = [];
        while (count($elegidos) < $cantidad) {
            $elegidos = [...$elegidos, ...Arr::shuffle($temas)];
        }

        return array_slice($elegidos, 0, $cantidad);
    }

    private function recordarTema(string $tema): void
    {
        $recientes = Cache::get(self::CLAVE_TEMAS_RECIENTES, []);
        $recientes[] = $tema;

        Cache::forever(self::CLAVE_TEMAS_RECIENTES, array_slice($recientes, -self::NO_REPETIR));
    }

    // ------------------------------------------------------------------
    // Prompts
    // ------------------------------------------------------------------

    private function textoEvitar(array $textos): string
    {
        return $textos
            ? "No repitas ninguna de estas preguntas:\n- " . implode("\n- ", $textos)
            : '';
    }

    /** Prompt para la materia Programación IV, centrado en un tema. */
    private function promptConTema(string $tema, string $evitar): string
    {
        $reglas = self::REGLAS;

        return <<<PROMPT
Generá UNA pregunta de trivia en español para estudiantes de Programación IV
de la Tecnicatura Universitaria en Programación (backend con PHP, Laravel y MySQL).

La pregunta tiene que ser SOBRE ESTE TEMA: "{$tema}".
Cuando el tema lo permita, aplicala a Laravel, PHP o MySQL.

Reglas:
{$reglas}
{$evitar}

Respondé SOLO con un JSON con este formato:
{"texto": "...", "opciones": ["...", "...", "...", "..."], "correcta": 0, "explicacion": "..."}
"correcta" es la posición (0 a 3) de la opción correcta.
PROMPT;
    }

    /** Prompt para un lote: una pregunta por cada tema, en el mismo orden. */
    private function promptLote(int $cantidad, array $temas, string $evitar): string
    {
        $reglas = self::REGLAS;

        if ($temas === []) {
            $pedido = "Generá {$cantidad} preguntas de trivia en español, distintas entre sí, sobre programación web\n"
                . "con Laravel o sobre bases de datos (SQL, migraciones, Eloquent, relaciones, índices, etc.).\n"
                . 'Nivel: estudiante de una tecnicatura universitaria en programación.';
        } else {
            $lista = implode("\n", array_map(fn ($t, $i) => ($i + 1) . ". {$t}", $temas, array_keys($temas)));
            $pedido = "Generá {$cantidad} preguntas de trivia en español para estudiantes de Programación IV\n"
                . "de la Tecnicatura Universitaria en Programación (backend con PHP, Laravel y MySQL).\n\n"
                . "Una pregunta SOBRE CADA UNO de estos temas, en este mismo orden:\n{$lista}\n\n"
                . 'Cuando el tema lo permita, aplicá la pregunta a Laravel, PHP o MySQL.';
        }

        return <<<PROMPT
{$pedido}

Reglas para cada pregunta:
{$reglas}
- Las {$cantidad} preguntas tienen que ser distintas entre sí.
{$evitar}

Respondé SOLO con un JSON con este formato (exactamente {$cantidad} elementos, en el orden pedido):
{"preguntas": [{"texto": "...", "opciones": ["...", "...", "...", "..."], "correcta": 0, "explicacion": "..."}]}
"correcta" es la posición (0 a 3) de la opción correcta.
PROMPT;
    }

    /** Prompt original (se usa si config('trivia.temas') está vacío). */
    private function promptGeneral(string $evitar): string
    {
        return <<<PROMPT
Generá UNA pregunta de trivia en español sobre programación web con Laravel
o sobre bases de datos (SQL, migraciones, Eloquent, relaciones, índices, etc.).
Nivel: estudiante de una tecnicatura universitaria en programación.
Tiene que tener exactamente 4 opciones cortas (máximo 60 caracteres cada una)
y una sola correcta.
{$evitar}
Respondé SOLO con un JSON con este formato:
{"texto": "...", "opciones": ["...", "...", "...", "..."], "correcta": 0, "explicacion": "..."}
"correcta" es la posición (0 a 3) de la opción correcta.
PROMPT;
    }
}
