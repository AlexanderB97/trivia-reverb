<?php

namespace App\Http\Controllers;

use App\Events\JugadorSalio;
use App\Events\JugadorUnido;
use App\Events\PartidaReiniciada;
use App\Events\PreguntaLanzada;
use App\Events\RespuestaRecibida;
use App\Events\ResultadoRevelado;
use App\Http\Middleware\EnsureTriviaHost;
use App\Models\Pregunta;
use App\Services\GeneradorPreguntas;
use App\Services\Jugadores;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trivia en vivo tipo Kahoot.
 *
 * Las preguntas salen del BANCO en base de datos (modelo Pregunta, tabla "preguntas"),
 * generado por lotes con `php artisan trivia:generar` o con el botón "Generar 10 más".
 * Solo si el banco está vacío se le pide una pregunta a la IA en el momento.
 *
 * El estado de la partida vive en el Cache (claves "trivia.*"):
 *  - trivia.config        ['total' => preguntas, 'segundos' => para responder, 'resultado' => segundos mostrando el resultado]
 *  - trivia.pregunta      la pregunta actual COMPLETA (incluida la correcta, nunca se manda tal cual)
 *  - trivia.temas_vistos  temas ya preguntados en esta partida (se priorizan los no vistos)
 *  - trivia.abierta       true mientras se aceptan respuestas
 *  - trivia.numero        número de la pregunta actual (1, 2, 3...)
 *  - trivia.lanzada_en    hora (ms) en que se lanzó la pregunta actual
 *  - trivia.cierra_en     hora (ms) límite para responder
 *  - trivia.respondieron  [nombre => ['opcion' => 0-3, 'puntos' => ganados]] de la ronda actual
 *  - trivia.puntajes      [nombre => puntos] acumulados en la partida
 *  - trivia.jugadores     [nombre en minúsculas => ['nombre' => ..., 'token' => ...]] anotados
 *  - trivia.hechas        textos de las preguntas ya usadas (para no repetir)
 */
class TriviaController extends Controller
{
    /** Clave del lock que protege las modificaciones del estado. */
    private const LOCK = 'trivia.lock';

    /** Margen (ms) para respuestas que salieron a tiempo pero tardaron en llegar por la red. */
    private const GRACIA_MS = 500;

    /** Mensaje para el jugador cuyo nombre o token no vale en la partida actual. */
    private const NO_ANOTADO = 'No estás anotado en esta partida. Volvé a ingresar tu nombre.';

    /** Preguntas que pide el botón "Generar 10 más". */
    private const LOTE_HOST = 10;

    /** Segundos máximos de PHP en las rutas que esperan a la IA (en Windows cuenta tiempo real). */
    private const LIMITE_IA = 60;

    private const LIMITE_LOTE = 120;

    /** Claves que se borran al reiniciar (el banco de preguntas en la base NO se toca). */
    private const CLAVES = [
        'trivia.temas_vistos',
        'trivia.config',
        'trivia.pregunta',
        'trivia.abierta',
        'trivia.numero',
        'trivia.lanzada_en',
        'trivia.cierra_en',
        'trivia.respondieron',
        'trivia.puntajes',
        'trivia.jugadores',
        'trivia.hechas',
        GeneradorPreguntas::CLAVE_TEMAS_RECIENTES,
    ];

    // ------------------------------------------------------------------
    // Vistas y acceso del host
    // ------------------------------------------------------------------

    public function host(Request $request)
    {
        $urlJugar = $this->urlJugar($request);

        return view('trivia.host', [
            'urlJugar' => $urlJugar,
            // El QR se genera acá, en el servidor (sin internet ni servicios externos),
            // como SVG en formato data URI para usarlo directo en un <img>
            'qrJugar' => (new QRCode(new QROptions(['eccLevel' => EccLevel::M])))->render($urlJugar),
            // "localhost" no existe para el celular: el host muestra un aviso
            'urlEsLocal' => (bool) preg_match('/localhost|127\.0\.0\.1|\[::1\]/i', $urlJugar),
        ]);
    }

    /**
     * URL que codifica el QR. En orden:
     *  1) TRIVIA_URL_JUGAR del .env, si está configurada;
     *  2) el origen de esta petición, si el host NO se abrió desde localhost (ej. http://192.168.1.50:8000);
     *  3) http://{REVERB_HOST}:{puerto de esta petición}/jugar (REVERB_HOST = IP de la notebook en la red).
     */
    private function urlJugar(Request $request): string
    {
        $configurada = trim((string) config('trivia.url_jugar'));

        if ($configurada !== '') {
            return $configurada;
        }

        if (! in_array($request->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return $request->getSchemeAndHttpHost().'/jugar';
        }

        $host = (string) config('broadcasting.connections.reverb.options.host') ?: $request->getHost();

        return 'http://'.$host.':'.$request->getPort().'/jugar';
    }

    public function jugar()
    {
        return view('trivia.jugar');
    }

    /**
     * POST /host/login {pin}
     * Si el PIN es correcto, marca la sesión como host.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string', 'max:100']]);

        $pinCorrecto = (string) config('trivia.host_pin');

        if ($pinCorrecto === '') {
            return redirect()->route('trivia.host')
                ->with('pin_error', 'El PIN del host no está configurado (TRIVIA_HOST_PIN en el .env).');
        }

        // hash_equals compara en tiempo constante: no da pistas de cuántos caracteres acertaste
        if (! hash_equals($pinCorrecto, (string) $request->input('pin'))) {
            return redirect()->route('trivia.host')->with('pin_error', 'PIN incorrecto.');
        }

        // Nuevo id de sesión al subir de permisos (evita "session fixation")
        $request->session()->regenerate();
        $request->session()->put(EnsureTriviaHost::SESION, true);

        return redirect()->route('trivia.host');
    }

    /**
     * POST /host/salir
     */
    public function salir(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsureTriviaHost::SESION);

        return redirect()->route('trivia.host');
    }

    // ------------------------------------------------------------------
    // Rutas públicas (jugadores)
    // ------------------------------------------------------------------

    /**
     * GET /trivia/estado?nombre=Ana
     * Sirve para que alguien que entra tarde (o recarga) se ponga al día.
     * Nunca incluye la respuesta correcta ni preguntas del banco.
     */
    public function estado(Request $request): JsonResponse
    {
        $config = Cache::get('trivia.config');
        $pregunta = Cache::get('trivia.pregunta');
        $abierta = (bool) Cache::get('trivia.abierta', false);
        $numero = (int) Cache::get('trivia.numero', 0);
        $total = (int) ($config['total'] ?? 0);
        $respondieron = Cache::get('trivia.respondieron', []);
        $nombre = trim((string) $request->query('nombre', ''));

        return response()->json([
            'config' => $config,
            'numero' => $numero,
            'total' => $total,
            'abierta' => $abierta,
            'terminada' => $numero > 0 && $numero >= $total && ! $abierta,
            'pregunta' => ($pregunta && $abierta) ? [
                'texto' => $pregunta['texto'],
                'opciones' => $pregunta['opciones'],
                'tema' => $pregunta['tema'] ?? null,
            ] : null,
            'segundos' => (int) ($config['segundos'] ?? 0),
            'cierraEn' => (int) Cache::get('trivia.cierra_en', 0),
            'ahora' => $this->ahoraMs(),
            'respondieron' => array_map('strval', array_keys($respondieron)),
            'jugadores' => $this->nombresJugadores(),
            // Si el jugador ya respondió esta ronda, le devolvemos qué eligió
            'miOpcion' => $nombre !== '' ? ($respondieron[$nombre]['opcion'] ?? null) : null,
            // Con la pregunta abierta van los puntos de ANTES de esta ronda: si no, mirando
            // quién sumó se podría deducir la respuesta correcta antes de revelar
            'ranking' => $this->ranking(ocultarRonda: $abierta),
            // Solo la cantidad (para "Banco: N preguntas disponibles" en el host), nunca las preguntas
            'banco' => Pregunta::disponibles()->count(),
        ]);
    }

    /**
     * POST /trivia/unirse {nombre, token?}
     * Anota al jugador. Los nombres son únicos sin distinguir mayúsculas.
     * Devuelve un token secreto: con él, el mismo jugador puede volver a entrar
     * (por ejemplo si recarga) y nadie más puede responder con su nombre.
     */
    public function unirse(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $nombre = trim($datos['nombre']);
        $token = (string) ($datos['token'] ?? '');
        $clave = mb_strtolower($nombre);

        try {
            $resultado = Cache::lock(self::LOCK, 10)->block(5, function () use ($nombre, $token, $clave) {
                $jugadores = Cache::get('trivia.jugadores', []);

                if (isset($jugadores[$clave])) {
                    // Es el mismo jugador volviendo (tiene el token correcto)
                    if ($token !== '' && hash_equals($jugadores[$clave]['token'], $token)) {
                        return ['jugador' => $jugadores[$clave], 'nuevo' => false];
                    }

                    return ['error' => "El nombre \"{$jugadores[$clave]['nombre']}\" ya está en uso. Elegí otro."];
                }

                $jugadores[$clave] = ['nombre' => $nombre, 'token' => Str::random(40)];
                Cache::forever('trivia.jugadores', $jugadores);

                // Entra al ranking con 0 puntos
                $puntajes = Cache::get('trivia.puntajes', []);
                $puntajes[$nombre] ??= 0;
                Cache::forever('trivia.puntajes', $puntajes);

                return ['jugador' => $jugadores[$clave], 'nuevo' => true, 'cantidad' => count($jugadores)];
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (isset($resultado['error'])) {
            return response()->json(['message' => $resultado['error']], 422);
        }

        if ($resultado['nuevo'] && $error = $this->transmitir(new JugadorUnido($nombre, $resultado['cantidad']))) {
            return $error;
        }

        return response()->json([
            'nombre' => $resultado['jugador']['nombre'],
            'token' => $resultado['jugador']['token'],
        ]);
    }

    /**
     * POST /trivia/responder {nombre, token, opcion}
     * Corrige en el servidor, calcula los puntos por velocidad y avisa que
     * alguien respondió (sin decir si acertó).
     */
    public function responder(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30'],
            'token' => ['required', 'string', 'max:64'],
            'opcion' => ['required', 'integer', 'between:0,3'],
        ]);

        $clave = mb_strtolower(trim($datos['nombre']));
        $token = (string) $datos['token'];
        $opcion = (int) $datos['opcion'];
        $ahora = $this->ahoraMs();

        try {
            // El lock hace que dos respuestas simultáneas se procesen una después de la otra,
            // así ninguna pisa a la otra al leer y guardar "respondieron" y "puntajes".
            $resultado = Cache::lock(self::LOCK, 10)->block(5, function () use ($clave, $token, $opcion, $ahora) {
                $jugador = Cache::get('trivia.jugadores', [])[$clave] ?? null;

                if (! $jugador || ! hash_equals($jugador['token'], $token)) {
                    return ['error' => self::NO_ANOTADO, 'reingresar' => true];
                }

                $nombre = $jugador['nombre'];
                $pregunta = Cache::get('trivia.pregunta');

                if (! $pregunta || ! Cache::get('trivia.abierta', false)) {
                    return ['error' => 'No hay ninguna pregunta abierta en este momento.'];
                }

                // El servidor también controla el tiempo: no confiamos en el reloj del navegador
                if ($ahora > (int) Cache::get('trivia.cierra_en', 0) + self::GRACIA_MS) {
                    return ['error' => '¡Se terminó el tiempo para responder!'];
                }

                $respondieron = Cache::get('trivia.respondieron', []);

                if (array_key_exists($nombre, $respondieron)) {
                    return ['error' => 'Ya respondiste esta pregunta.'];
                }

                $segundos = (int) Cache::get('trivia.config')['segundos'];
                $tardanza = $ahora - (int) Cache::get('trivia.lanzada_en', $ahora);
                $puntos = $opcion === $pregunta['correcta'] ? $this->puntosPorVelocidad($tardanza, $segundos) : 0;

                $respondieron[$nombre] = ['opcion' => $opcion, 'puntos' => $puntos];

                $puntajes = Cache::get('trivia.puntajes', []);
                $puntajes[$nombre] = ($puntajes[$nombre] ?? 0) + $puntos;

                Cache::forever('trivia.respondieron', $respondieron);
                Cache::forever('trivia.puntajes', $puntajes);

                return [
                    'nombre' => $nombre,
                    'cantidad' => count($respondieron),
                    'jugadores' => count(Cache::get('trivia.jugadores', [])),
                ];
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (isset($resultado['error'])) {
            return response()->json([
                'message' => $resultado['error'],
                'reingresar' => $resultado['reingresar'] ?? false,
            ], 422);
        }

        if ($error = $this->transmitir(new RespuestaRecibida($resultado['nombre'], $resultado['cantidad'], $resultado['jugadores']))) {
            return $error;
        }

        // A propósito NO devolvemos si acertó: eso se ve recién al revelar
        return response()->json(['ok' => true, 'cantidad' => $resultado['cantidad']]);
    }

    /**
     * POST /trivia/reconectar {nombre, token}
     * El navegador guardó el nombre y el token: al volver a abrir /jugar pregunta si
     * siguen valiendo en la partida actual. Si valen, el jugador entra directo (con sus
     * puntos); si no (por ejemplo, se reinició la partida), tiene que anotarse de nuevo.
     * A diferencia de /trivia/unirse, nunca anota a nadie.
     */
    public function reconectar(Request $request, Jugadores $jugadores): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30'],
            'token' => ['required', 'string', 'max:64'],
        ]);

        $jugador = $jugadores->conToken($datos['nombre'], $datos['token']);

        if (! $jugador) {
            return $this->noAnotado();
        }

        // Con la pregunta abierta, los puntos son los de antes de esa ronda (como en estado())
        $fila = collect($this->ranking(ocultarRonda: (bool) Cache::get('trivia.abierta', false)))
            ->firstWhere('nombre', $jugador['nombre']);

        return response()->json([
            'nombre' => $jugador['nombre'],
            'token' => $jugador['token'],
            'puntos' => (int) ($fila['puntos'] ?? 0),
        ]);
    }

    /**
     * POST /trivia/salir {nombre, token}
     * Botón "Salir de la partida": borra al jugador (su nombre queda libre y sus puntos
     * desaparecen del ranking) y avisa con el evento JugadorSalio.
     */
    public function abandonar(Request $request, Jugadores $jugadores): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30'],
            'token' => ['required', 'string', 'max:64'],
        ]);

        try {
            $sacado = Cache::lock(self::LOCK, 10)->block(5, function () use ($datos, $jugadores) {
                $jugador = $jugadores->conToken($datos['nombre'], $datos['token']);

                return $jugador ? $this->sacarJugador($jugador['nombre']) : null;
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (! $sacado) {
            return $this->noAnotado();
        }

        return $this->despedir($sacado, quitado: false);
    }

    // ------------------------------------------------------------------
    // Rutas del host (protegidas con EnsureTriviaHost)
    // ------------------------------------------------------------------

    /**
     * POST /trivia/empezar {total, segundos, resultado}
     * Guarda la configuración y pone los puntajes en 0 (los jugadores anotados se mantienen).
     */
    public function empezar(Request $request): JsonResponse
    {
        $config = $request->validate([
            'total' => ['required', 'integer', 'between:1,50'],
            'segundos' => ['required', 'integer', 'between:5,120'],
            'resultado' => ['required', 'integer', 'between:3,60'],
        ]);
        $config = array_map('intval', $config);

        try {
            Cache::lock(self::LOCK, 10)->block(5, function () use ($config) {
                Cache::forever('trivia.config', $config);
                Cache::forever('trivia.numero', 0);
                Cache::forever('trivia.abierta', false);
                Cache::forget('trivia.pregunta');
                Cache::forever('trivia.respondieron', []);
                Cache::forget('trivia.temas_vistos'); // partida nueva: todos los temas vuelven a ser "no vistos"

                $puntajes = [];
                foreach (Cache::get('trivia.jugadores', []) as $jugador) {
                    $puntajes[$jugador['nombre']] = 0;
                }
                Cache::forever('trivia.puntajes', $puntajes);
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        return response()->json(['ok' => true, 'config' => $config]);
    }

    /**
     * POST /trivia/preparar
     * Prefetch (se llama mientras se muestra el resultado). Si el banco tiene preguntas
     * disponibles no hace nada (sacar una de la base es instantáneo). Solo si el banco
     * está vacío le pide UNA a la IA y la guarda en el banco, así la próxima no espera.
     */
    public function preparar(GeneradorPreguntas $generador): JsonResponse
    {
        if (Pregunta::disponibles()->exists()) {
            return response()->json(['ok' => true, 'yaEstaba' => true, 'banco' => Pregunta::disponibles()->count()]);
        }

        set_time_limit(self::LIMITE_IA);

        try {
            Pregunta::guardarSiNoExiste($this->generar($generador));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $this->mensajeErrorIa($e, 'No se pudo preparar la siguiente pregunta con la IA.'),
            ], 502);
        }

        return response()->json(['ok' => true, 'yaEstaba' => false, 'banco' => Pregunta::disponibles()->count()]);
    }

    /**
     * POST /trivia/generar-lote
     * Botón "Generar 10 más": pide un lote a la IA (un solo pedido) y lo guarda en el banco.
     */
    public function generarLote(GeneradorPreguntas $generador): JsonResponse
    {
        set_time_limit(self::LIMITE_LOTE);

        try {
            $guardadas = $generador->generarLote(self::LOTE_HOST);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $this->mensajeErrorIa($e, 'No se pudo generar el lote de preguntas con la IA.'),
            ], $e->getCode() === GeneradorPreguntas::CUOTA_AGOTADA ? 429 : 502);
        }

        return response()->json(['guardadas' => $guardadas, 'banco' => Pregunta::disponibles()->count()]);
    }

    /**
     * POST /trivia/pregunta
     * Lanza la siguiente pregunta: la saca del banco (no usada, priorizando temas no vistos
     * en la partida) y la marca como usada. Solo si el banco está vacío la pide a la IA
     * en el momento y la guarda en el banco.
     * Guarda la hora de cierre y la transmite SIN la correcta.
     */
    public function pregunta(GeneradorPreguntas $generador): JsonResponse
    {
        $config = Cache::get('trivia.config');

        if ($error = $this->sePuedeLanzar($config)) {
            return response()->json(['message' => $error], 422);
        }

        try {
            $pregunta = Cache::lock(self::LOCK, 10)->block(5, fn () => $this->tomarDelBanco());
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (! $pregunta) {
            // Respaldo: banco vacío, se genera una en el momento y se guarda (ya marcada como usada)
            set_time_limit(self::LIMITE_IA);

            try {
                $nueva = $this->generar($generador);
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => $this->mensajeErrorIa($e, 'El banco está vacío y no se pudo generar la pregunta con la IA. Probá de nuevo en unos segundos.'),
                ], 502);
            }

            $guardada = Pregunta::guardarSiNoExiste([...$nueva, 'usada_en' => now()]);
            $pregunta = $guardada ? $guardada->paraJuego() : [...$nueva, 'id' => null];
        }

        try {
            $lanzada = Cache::lock(self::LOCK, 10)->block(5, function () use ($pregunta) {
                $config = Cache::get('trivia.config');

                // Volvemos a chequear: mientras esperábamos a la IA pudo cambiar el estado
                if ($error = $this->sePuedeLanzar($config)) {
                    // No la desperdiciamos: vuelve a estar disponible en el banco
                    if ($pregunta['id']) {
                        Pregunta::whereKey($pregunta['id'])->update(['usada_en' => null]);
                    }

                    return ['error' => $error];
                }

                if ($pregunta['tema'] ?? null) {
                    Cache::forever('trivia.temas_vistos', [...Cache::get('trivia.temas_vistos', []), $pregunta['tema']]);
                }

                $numero = (int) Cache::get('trivia.numero', 0) + 1;
                $ahora = $this->ahoraMs();
                $cierraEn = $ahora + $config['segundos'] * 1000;

                Cache::forever('trivia.pregunta', $pregunta);
                Cache::forever('trivia.abierta', true);
                Cache::forever('trivia.numero', $numero);
                Cache::forever('trivia.lanzada_en', $ahora);
                Cache::forever('trivia.cierra_en', $cierraEn);
                Cache::forever('trivia.respondieron', []);
                Cache::forever('trivia.hechas', [...Cache::get('trivia.hechas', []), $pregunta['texto']]);

                return [
                    'numero' => $numero,
                    'total' => $config['total'],
                    'texto' => $pregunta['texto'],
                    'opciones' => $pregunta['opciones'],
                    'tema' => $pregunta['tema'] ?? null,
                    'segundos' => $config['segundos'],
                    'cierraEn' => $cierraEn,
                    'ahora' => $ahora,
                ];
            });
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (isset($lanzada['error'])) {
            return response()->json(['message' => $lanzada['error']], 422);
        }

        // Al jugador solo le llega lo necesario para jugar (nada de "correcta")
        if ($error = $this->transmitir(new PreguntaLanzada(...$lanzada))) {
            return $error;
        }

        return response()->json($lanzada);
    }

    /**
     * POST /trivia/revelar
     * Cierra la pregunta y transmite la correcta, la explicación y el ranking.
     * Se puede llamar antes de que venza el tiempo ("Saltar a revelar").
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

        $numero = (int) Cache::get('trivia.numero', 0);
        $total = (int) (Cache::get('trivia.config')['total'] ?? $numero);

        $datos = [
            'correcta' => $pregunta['correcta'],
            'explicacion' => $pregunta['explicacion'],
            'ranking' => $this->ranking(),
            'numero' => $numero,
            'total' => $total,
            'final' => $numero >= $total,
        ];

        if ($error = $this->transmitir(new ResultadoRevelado(...$datos))) {
            return $error;
        }

        return response()->json($datos);
    }

    /**
     * POST /trivia/reiniciar
     * Borra puntajes, jugadores, preguntas hechas y el estado de la ronda.
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

        // Los jugadores vuelven a "Esperando pregunta..." y se vuelven a anotar solos
        if ($error = $this->transmitir(new PartidaReiniciada)) {
            return $error;
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /trivia/quitar {nombre}
     * El host saca a un jugador de la partida (por ejemplo, uno que quedó "huérfano"
     * o un nombre inapropiado). Igual que si el jugador saliera, pero sin pedir su token.
     */
    public function quitar(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:30']]);

        try {
            $sacado = Cache::lock(self::LOCK, 10)->block(5, fn () => $this->sacarJugador($datos['nombre']));
        } catch (LockTimeoutException) {
            return $this->ocupado();
        }

        if (! $sacado) {
            return response()->json(['message' => 'Ese jugador ya no está en la partida.'], 422);
        }

        return $this->despedir($sacado, quitado: true);
    }

    // ------------------------------------------------------------------
    // Ayudantes
    // ------------------------------------------------------------------

    /**
     * Borra a un jugador de la partida (se llama dentro del lock): libera su nombre y
     * saca sus puntos y su respuesta de la ronda. Devuelve null si no estaba anotado.
     *
     * @return array{nombre: string, cantidad: int}|null "cantidad" son los que quedan
     */
    private function sacarJugador(string $nombre): ?array
    {
        $clave = mb_strtolower(trim($nombre));
        $jugadores = Cache::get(Jugadores::CLAVE, []);

        if (! isset($jugadores[$clave])) {
            return null;
        }

        $nombre = $jugadores[$clave]['nombre'];
        unset($jugadores[$clave]);
        Cache::forever(Jugadores::CLAVE, $jugadores);

        foreach (['trivia.puntajes', 'trivia.respondieron'] as $claveCache) {
            $datos = Cache::get($claveCache, []);
            unset($datos[$nombre]);
            Cache::forever($claveCache, $datos);
        }

        return ['nombre' => $nombre, 'cantidad' => count($jugadores)];
    }

    /**
     * Avisa por JugadorSalio que el jugador ya no está (salió o lo quitó el host).
     *
     * @param  array{nombre: string, cantidad: int}  $sacado
     */
    private function despedir(array $sacado, bool $quitado): JsonResponse
    {
        $evento = new JugadorSalio(
            $sacado['nombre'],
            $sacado['cantidad'],
            $quitado,
            // Con la pregunta abierta, sin los puntos de la ronda (no delata quién acertó)
            $this->ranking(ocultarRonda: (bool) Cache::get('trivia.abierta', false)),
        );

        if ($error = $this->transmitir($evento)) {
            return $error;
        }

        return response()->json(['ok' => true, 'nombre' => $sacado['nombre'], 'cantidad' => $sacado['cantidad']]);
    }

    /** Respuesta para quien manda un nombre o token que no vale en la partida actual. */
    private function noAnotado(): JsonResponse
    {
        return response()->json(['message' => self::NO_ANOTADO, 'reingresar' => true], 422);
    }

    /**
     * Puntos estilo Kahoot: correcta al instante = 1000, justo al final = 500.
     * Baja en línea recta según la fracción del tiempo que tardó.
     */
    private function puntosPorVelocidad(int $tardanzaMs, int $segundos): int
    {
        $fraccion = min(max($tardanzaMs / ($segundos * 1000), 0), 1);

        return (int) round(1000 - 500 * $fraccion);
    }

    /** Devuelve un mensaje de error si no se puede lanzar otra pregunta, o null si se puede. */
    private function sePuedeLanzar(?array $config): ?string
    {
        if (! $config) {
            return 'Primero configurá y empezá la partida.';
        }

        if (Cache::get('trivia.abierta', false)) {
            return 'Todavía hay una pregunta abierta: revelala primero.';
        }

        if ((int) Cache::get('trivia.numero', 0) >= $config['total']) {
            return 'La partida ya terminó. Empezá una nueva.';
        }

        return null;
    }

    /**
     * Saca una pregunta disponible del banco y la marca como usada (se llama dentro del lock).
     * Prioriza temas que todavía no salieron en esta partida y nunca repite una ya hecha
     * (por si se recicló el banco a mitad de partida). Devuelve null si el banco está vacío.
     */
    private function tomarDelBanco(): ?array
    {
        $vistos = Cache::get('trivia.temas_vistos', []);
        $hechas = array_map([Pregunta::class, 'hashDe'], Cache::get('trivia.hechas', []));

        $base = fn () => Pregunta::disponibles()->whereNotIn('texto_hash', $hechas);

        // 1) Un tema no visto (o sin tema); 2) si no queda ninguno, cualquiera disponible
        $pregunta = $base()
            ->when($vistos, fn ($q) => $q->where(fn ($w) => $w->whereNotIn('tema', $vistos)->orWhereNull('tema')))
            ->inRandomOrder()
            ->first()
            ?? $base()->inRandomOrder()->first();

        if (! $pregunta) {
            return null;
        }

        $pregunta->update(['usada_en' => now()]);

        return $pregunta->paraJuego();
    }

    /** Mensaje para el host cuando falla la IA (con aviso especial si se agotó la cuota). */
    private function mensajeErrorIa(Throwable $e, string $mensaje): string
    {
        return $e->getCode() === GeneradorPreguntas::CUOTA_AGOTADA
            ? 'Se agotó la cuota diaria de Gemini (429). Cargá el banco con "php artisan trivia:generar" cuando se renueve.'
            : $mensaje;
    }

    /** Respaldo: pide UNA pregunta a la IA en el momento (sin repetir las ya hechas) y la normaliza. */
    private function generar(GeneradorPreguntas $generador): array
    {
        $nueva = $generador->generar(array_slice(Cache::get('trivia.hechas', []), -30));

        return [
            'texto' => (string) $nueva['texto'],
            'opciones' => array_values(array_map('strval', $nueva['opciones'])),
            'correcta' => (int) $nueva['correcta'],
            'explicacion' => (string) ($nueva['explicacion'] ?? ''),
            'tema' => isset($nueva['tema']) ? (string) $nueva['tema'] : null,
        ];
    }

    /**
     * Ranking ordenado de mayor a menor puntaje (y por nombre si empatan).
     * "ganados" son los puntos que sumó en la última pregunta.
     * "puesto" respeta los empates: mismo puntaje = mismo puesto (1, 2, 2, 4).
     *
     * Con $ocultarRonda (pregunta todavía abierta) devuelve el ranking como estaba ANTES
     * de la pregunta actual: sin los puntos de esta ronda y con "ganados" en 0. Los puntos
     * de la ronda se ven recién al revelar; antes delatarían quién acertó.
     *
     * @return array<int, array{nombre: string, puntos: int, ganados: int, puesto: int}>
     */
    private function ranking(bool $ocultarRonda = false): array
    {
        $respondieron = Cache::get('trivia.respondieron', []);
        $ranking = [];

        foreach (Cache::get('trivia.puntajes', []) as $nombre => $puntos) {
            $ganados = (int) ($respondieron[$nombre]['puntos'] ?? 0);

            $ranking[] = [
                'nombre' => (string) $nombre,
                'puntos' => $ocultarRonda ? (int) $puntos - $ganados : (int) $puntos,
                'ganados' => $ocultarRonda ? 0 : $ganados,
            ];
        }

        usort($ranking, fn ($a, $b) => [$b['puntos'], $a['nombre']] <=> [$a['puntos'], $b['nombre']]);

        // Si tiene los mismos puntos que el anterior, hereda su puesto
        foreach ($ranking as $i => &$fila) {
            $fila['puesto'] = ($i > 0 && $fila['puntos'] === $ranking[$i - 1]['puntos'])
                ? $ranking[$i - 1]['puesto']
                : $i + 1;
        }
        unset($fila);

        return $ranking;
    }

    /** @return array<int, string> */
    private function nombresJugadores(): array
    {
        return array_values(array_map(fn ($j) => $j['nombre'], Cache::get('trivia.jugadores', [])));
    }

    /** Hora actual en milisegundos. */
    private function ahoraMs(): int
    {
        return (int) floor(microtime(true) * 1000);
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
