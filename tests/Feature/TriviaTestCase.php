<?php

namespace Tests\Feature;

use App\Events\JugadorSalio;
use App\Events\JugadorUnido;
use App\Events\PartidaReiniciada;
use App\Events\PreguntaLanzada;
use App\Events\RespuestaRecibida;
use App\Events\ResultadoRevelado;
use App\Http\Middleware\EnsureTriviaHost;
use App\Models\Pregunta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base de los tests de la trivia: prepara el entorno y junta los pasos que se repiten
 * (entrar como host, cargar el banco, empezar la partida, anotar jugadores, responder).
 *
 * No termina en "Test.php" a propósito: PHPUnit no la corre como si fuera un test.
 */
abstract class TriviaTestCase extends TestCase
{
    use RefreshDatabase;

    /** PIN del host en testing (TRIVIA_HOST_PIN en phpunit.xml). */
    protected const PIN = '123456';

    /** Posición de la opción correcta de la pregunta de prueba. */
    protected const CORRECTA = 2;

    /** Una opción incorrecta de la pregunta de prueba. */
    protected const INCORRECTA = 0;

    /** Explicación de la pregunta de prueba (texto único, fácil de buscar si se filtra). */
    protected const EXPLICACION = 'EXPLICACION-SECRETA: make:migration crea el archivo de migración.';

    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas usan @vite: en los tests no hace falta tener el frontend compilado
        $this->withoutVite();

        // Red de seguridad: si un test intenta llamar a Gemini (o a cualquier URL) sin
        // haberla simulado con Http::fake(), falla en vez de salir a internet
        Http::preventStrayRequests();

        // Los eventos de la trivia no se transmiten: quedan anotados para poder verificarlos
        Event::fake([
            JugadorSalio::class,
            JugadorUnido::class,
            PartidaReiniciada::class,
            PreguntaLanzada::class,
            RespuestaRecibida::class,
            ResultadoRevelado::class,
        ]);
    }

    /**
     * Marca la sesión como host autorizado (lo mismo que deja el login con el PIN correcto).
     */
    protected function comoHost(): static
    {
        return $this->withSession([EnsureTriviaHost::SESION => true]);
    }

    /**
     * Guarda una pregunta disponible en el banco. La correcta es la opción self::CORRECTA.
     *
     * @param  array{tema?: ?string, texto?: string, opciones?: array<int, string>, correcta?: int, explicacion?: string, usada_en?: mixed}  $datos
     */
    protected function crearPreguntaEnBanco(array $datos = []): Pregunta
    {
        return Pregunta::guardarSiNoExiste([
            ...$this->datosPregunta(),
            ...$datos,
        ]);
    }

    /**
     * Pregunta de prueba, con el mismo formato que devuelve la IA.
     *
     * @return array{tema: string, texto: string, opciones: array<int, string>, correcta: int, explicacion: string}
     */
    protected function datosPregunta(): array
    {
        return [
            'tema' => 'Migraciones y esquema de base de datos en Laravel',
            'texto' => '¿Qué comando de Artisan crea una migración?',
            'opciones' => ['make:model', 'make:seeder', 'make:migration', 'make:table'],
            'correcta' => self::CORRECTA,
            'explicacion' => self::EXPLICACION,
        ];
    }

    /**
     * El host configura y empieza la partida.
     */
    protected function empezarPartida(int $total = 3, int $segundos = 20): void
    {
        $this->comoHost()
            ->postJson('/trivia/empezar', ['total' => $total, 'segundos' => $segundos, 'resultado' => 5])
            ->assertOk();
    }

    /**
     * El host lanza la siguiente pregunta.
     */
    protected function lanzarPregunta(): TestResponse
    {
        return $this->comoHost()->postJson('/trivia/pregunta');
    }

    /**
     * Deja la partida lista para responder: una pregunta en el banco, partida empezada
     * y esa pregunta lanzada (abierta).
     */
    protected function abrirPregunta(int $segundos = 20): Pregunta
    {
        $pregunta = $this->crearPreguntaEnBanco();

        $this->empezarPartida(segundos: $segundos);
        $this->lanzarPregunta()->assertOk();

        return $pregunta;
    }

    /**
     * Anota a un jugador y devuelve su token secreto.
     */
    protected function unirJugador(string $nombre): string
    {
        return $this->postJson('/trivia/unirse', ['nombre' => $nombre])
            ->assertOk()
            ->json('token');
    }

    /**
     * Un jugador responde la pregunta abierta.
     */
    protected function responder(string $nombre, string $token, int $opcion): TestResponse
    {
        return $this->postJson('/trivia/responder', [
            'nombre' => $nombre,
            'token' => $token,
            'opcion' => $opcion,
        ]);
    }

    /**
     * El host revela el resultado de la pregunta abierta.
     */
    protected function revelar(): TestResponse
    {
        return $this->comoHost()->postJson('/trivia/revelar');
    }

    /**
     * Fila del ranking (según /trivia/estado) de un jugador.
     * Ojo: con la pregunta abierta el estado no muestra los puntos de la ronda en curso,
     * así que para verificar lo que sumó una respuesta hay que revelar() antes.
     *
     * @return array{nombre: string, puntos: int, ganados: int, puesto: int}
     */
    protected function filaDelRanking(string $nombre): array
    {
        return collect($this->getJson('/trivia/estado')->assertOk()->json('ranking'))
            ->firstWhere('nombre', $nombre);
    }

    /**
     * Hora actual en milisegundos (el mismo formato que usa el estado de la partida).
     */
    protected function ahoraMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * Respuesta simulada de Gemini con una pregunta válida.
     *
     * @param  array<string, mixed>  $pregunta
     * @return array<string, mixed>
     */
    protected function respuestaDeGemini(array $pregunta): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($pregunta)]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }
}
