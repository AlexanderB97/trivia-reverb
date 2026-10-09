<?php

namespace App\Http\Controllers;

use App\Events\PreguntaLanzada;
use App\Events\RespuestaRecibida;
use App\Events\ResultadoRevelado;
use App\Services\GeneradorPreguntas;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Trivia en vivo tipo Kahoot.
 *
 * Todo el estado de la partida vive en el Cache (claves "trivia.*"):
 *  - trivia.pregunta      la pregunta actual COMPLETA (incluida la correcta, nunca se manda tal cual)
 *  - trivia.abierta       true mientras se aceptan respuestas
 *  - trivia.numero        número de la pregunta actual (1, 2, 3...)
 *  - trivia.respondieron  [nombre => opción elegida] de la ronda actual
 *  - trivia.puntajes      [nombre => puntos] acumulados en la partida
 *  - trivia.hechas        textos de las preguntas ya usadas (para no repetir)
 */
class TriviaController extends Controller
{
    /** Clave del lock que protege las modificaciones del estado. */
    private const LOCK = 'trivia.lock';

    /** Todas las claves de estado (se usan al reiniciar). */
    private const CLAVES = [
        'trivia.pregunta',
        'trivia.abierta',
        'trivia.numero',
        'trivia.respondieron',
        'trivia.puntajes',
        'trivia.hechas',
    ];

    public function host()
    {
        return view('trivia.host');
    }

    public function jugar()
    {
        return view('trivia.jugar');
    }

    /**
     * GET /trivia/estado?nombre=Ana
     * Sirve para que alguien que entra tarde (o recarga) se ponga al día.
     * Nunca incluye la respuesta correcta mientras la pregunta esté abierta.
     */
    public function estado(Request $request): JsonResponse
    {
        $pregunta = Cache::get('trivia.pregunta');
        $abierta = (bool) Cache::get('trivia.abierta', false);
        $respondieron = Cache::get('trivia.respondieron', []);
        $nombre = trim((string) $request->query('nombre', ''));

        return response()->json([
            'numero' => (int) Cache::get('trivia.numero', 0),
            'abierta' => $abierta,
            'pregunta' => ($pregunta && $abierta) ? [
                'texto' => $pregunta['texto'],
                'opciones' => $pregunta['opciones'],
            ] : null,
            'respondieron' => array_keys($respondieron),
            // Si el jugador ya respondió esta ronda, le devolvemos qué eligió
            'miOpcion' => $nombre !== '' ? ($respondieron[$nombre] ?? null) : null,
            'ranking' => $this->ranking(),
        ]);
    }

    /**
     * POST /trivia/pregunta
     * Pide una pregunta nueva a la IA, la guarda y la transmite SIN la correcta.
     */
    public function pregunta(GeneradorPreguntas $generador): JsonResponse
    {
        $hechas = Cache::get('trivia.hechas', []);

        try {
            // Le pasamos las últimas preguntas hechas para que no las repita
            $nueva = $generador->generar(array_slice($hechas, -30));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo generar la pregunta con la IA. Probá de nuevo en unos segundos.',
            ], 502);
        }

        $pregunta = [
            'texto' => (string) $nueva['texto'],
            'opciones' => array_values(array_map('strval', $nueva['opciones'])),
            'correcta' => (int) $nueva['correcta'],
            'explicacion' => (string) ($nueva['explicacion'] ?? ''),
        ];

        try {
            $numero = Cache::lock(self::LOCK, 10)->block(5, function () use ($pregunta, $hechas) {
                $numero = (int) Cache::get('trivia.numero', 0) + 1;

                Cache::forever('trivia.pregunta', $pregunta);
                Cache::forever('trivia.abierta', true);
                Cache::forever('trivia.numero', $numero);
                Cache::forever('trivia.respondieron', []);
                Cache::forever('trivia.hechas', [...$hechas, $pregunta['texto']]);

                return $numero;
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        // Al jugador solo le llegan número, texto y opciones
        if ($error = $this->transmitir(new PreguntaLanzada($numero, $pregunta['texto'], $pregunta['opciones']))) {
            return $error;
        }

        return response()->json([
            'numero' => $numero,
            'texto' => $pregunta['texto'],
            'opciones' => $pregunta['opciones'],
        ]);
    }

    /**
     * POST /trivia/responder {nombre, opcion}
     * Corrige en el servidor y avisa que alguien respondió (sin decir si acertó).
     */
    public function responder(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30'],
            'opcion' => ['required', 'integer', 'between:0,3'],
        ]);

        $nombre = trim($datos['nombre']);
        $opcion = (int) $datos['opcion'];

        try {
            // El lock hace que dos respuestas simultáneas se procesen una después de la otra,
            // así ninguna pisa a la otra al leer y guardar "respondieron" y "puntajes".
            $resultado = Cache::lock(self::LOCK, 10)->block(5, function () use ($nombre, $opcion) {
                $pregunta = Cache::get('trivia.pregunta');

                if (! $pregunta || ! Cache::get('trivia.abierta', false)) {
                    return ['error' => 'No hay ninguna pregunta abierta en este momento.'];
                }

                $respondieron = Cache::get('trivia.respondieron', []);

                if (array_key_exists($nombre, $respondieron)) {
                    return ['error' => "\"{$nombre}\" ya respondió esta pregunta."];
                }

                $respondieron[$nombre] = $opcion;

                // Todo jugador que responde entra al ranking (aunque sea con 0 puntos)
                $puntajes = Cache::get('trivia.puntajes', []);
                $puntajes[$nombre] = ($puntajes[$nombre] ?? 0) + ($opcion === $pregunta['correcta'] ? 1 : 0);

                Cache::forever('trivia.respondieron', $respondieron);
                Cache::forever('trivia.puntajes', $puntajes);

                return ['cantidad' => count($respondieron)];
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (isset($resultado['error'])) {
            return response()->json(['message' => $resultado['error']], 422);
        }

        if ($error = $this->transmitir(new RespuestaRecibida($nombre, $resultado['cantidad']))) {
            return $error;
        }

        // A propósito NO devolvemos si acertó: eso se ve recién al revelar
        return response()->json(['ok' => true, 'cantidad' => $resultado['cantidad']]);
    }

    /**
     * POST /trivia/revelar
     * Cierra la pregunta y transmite la correcta, la explicación y el ranking.
     */
    public function revelar(): JsonResponse
    {
        try {
            $pregunta = Cache::lock(self::LOCK, 10)->block(5, function () {
                $pregunta = Cache::get('trivia.pregunta');

                if (! $pregunta || ! Cache::get('trivia.abierta', false)) {
                    return null;
                }

                Cache::forever('trivia.abierta', false);

                return $pregunta;
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (! $pregunta) {
            return response()->json(['message' => 'No hay ninguna pregunta abierta para revelar.'], 422);
        }

        $ranking = $this->ranking();

        if ($error = $this->transmitir(new ResultadoRevelado($pregunta['correcta'], $pregunta['explicacion'], $ranking))) {
            return $error;
        }

        return response()->json([
            'correcta' => $pregunta['correcta'],
            'explicacion' => $pregunta['explicacion'],
            'ranking' => $ranking,
        ]);
    }

    /**
     * POST /trivia/reiniciar
     * Borra puntajes, preguntas hechas y el estado de la ronda.
     */
    public function reiniciar(): JsonResponse
    {
        try {
            Cache::lock(self::LOCK, 10)->block(5, function () {
                foreach (self::CLAVES as $clave) {
                    Cache::forget($clave);
                }
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Ranking ordenado de mayor a menor puntaje (y por nombre si empatan).
     *
     * @return array<int, array{nombre: string, puntos: int}>
     */
    private function ranking(): array
    {
        $ranking = [];

        foreach (Cache::get('trivia.puntajes', []) as $nombre => $puntos) {
            $ranking[] = ['nombre' => (string) $nombre, 'puntos' => (int) $puntos];
        }

        usort($ranking, fn ($a, $b) => [$b['puntos'], $a['nombre']] <=> [$a['puntos'], $b['nombre']]);

        return $ranking;
    }

    /**
     * Transmite el evento por Reverb. Si falla (por ejemplo, Reverb apagado),
     * devuelve una respuesta JSON de error en vez de romper la página.
     */
    private function transmitir(object $evento): ?JsonResponse
    {
        try {
            broadcast($evento);

            return null;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Se guardó el cambio, pero no se pudo transmitir en vivo. ¿Está corriendo "php artisan reverb:start"?',
            ], 503);
        }
    }

    private function ocupado(): JsonResponse
    {
        return response()->json(['message' => 'El servidor está ocupado, probá de nuevo.'], 503);
    }
}
