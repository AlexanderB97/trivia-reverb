<?php

namespace Tests\Feature;

use App\Events\PreguntaLanzada;
use App\Models\Pregunta;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Banco de preguntas: las preguntas salen de la base de datos y la IA (Gemini) es solo
 * el respaldo para cuando el banco está vacío. Gemini siempre está simulada con Http::fake().
 */
class BancoPreguntasTest extends TriviaTestCase
{
    /** Las llamadas a Gemini van todas a este dominio. */
    private const GEMINI = 'generativelanguage.googleapis.com/*';

    /**
     * Con preguntas en el banco, lanzar una no llama a la IA y la marca como usada.
     */
    public function test_con_preguntas_en_el_banco_no_se_llama_a_la_ia_y_se_marca_como_usada(): void
    {
        Http::fake();
        $pregunta = $this->crearPreguntaEnBanco();
        $this->empezarPartida();

        $this->lanzarPregunta()
            ->assertOk()
            ->assertJsonPath('texto', $pregunta->texto)
            ->assertJsonPath('numero', 1);

        Http::assertNothingSent();
        $this->assertNotNull($pregunta->fresh()->usada_en);
        $this->assertSame(0, Pregunta::disponibles()->count());
        Event::assertDispatched(PreguntaLanzada::class, 1);
    }

    /**
     * Una pregunta ya usada no vuelve a salir: se lanza la que queda disponible.
     */
    public function test_no_se_repite_una_pregunta_ya_usada(): void
    {
        Http::fake();
        $this->crearPreguntaEnBanco(['texto' => '¿Pregunta ya usada?', 'usada_en' => now()]);
        $disponible = $this->crearPreguntaEnBanco(['texto' => '¿Pregunta disponible?']);
        $this->empezarPartida();

        $this->lanzarPregunta()->assertOk()->assertJsonPath('texto', $disponible->texto);

        Http::assertNothingSent();
    }

    /**
     * Con el banco vacío se pide UNA pregunta a la IA, se lanza y queda guardada en el
     * banco ya marcada como usada.
     */
    public function test_con_el_banco_vacio_se_pide_la_pregunta_a_la_ia_y_se_guarda(): void
    {
        $datos = $this->datosPregunta();
        Http::fake([self::GEMINI => Http::response($this->respuestaDeGemini($datos))]);
        $this->empezarPartida();

        $this->lanzarPregunta()
            ->assertOk()
            ->assertJsonPath('texto', $datos['texto'])
            ->assertJsonMissingPath('correcta');

        Http::assertSentCount(1);
        $guardada = Pregunta::sole();
        $this->assertSame($datos['texto'], $guardada->texto);
        $this->assertSame(self::CORRECTA, $guardada->correcta);
        $this->assertNotNull($guardada->usada_en);
    }

    /**
     * Si la IA falla y el banco está vacío, el host recibe un 502 con un mensaje claro
     * y no se lanza ninguna pregunta.
     */
    public function test_si_la_ia_falla_y_el_banco_esta_vacio_devuelve_502_con_mensaje_claro(): void
    {
        Exceptions::fake(); // el error se reporta, pero no ensucia el log de los tests
        Http::fake([self::GEMINI => Http::response(['error' => ['message' => 'The model is overloaded.']], 503)]);
        $this->empezarPartida();

        $this->lanzarPregunta()
            ->assertStatus(502)
            ->assertExactJson([
                'message' => 'El banco está vacío y no se pudo generar la pregunta con la IA. Probá de nuevo en unos segundos.',
            ]);

        Exceptions::assertReported(RuntimeException::class);
        Event::assertNotDispatched(PreguntaLanzada::class);
        $this->getJson('/trivia/estado')
            ->assertJsonPath('abierta', false)
            ->assertJsonPath('numero', 0);
    }

    /**
     * Si la IA devuelve algo que no es una pregunta válida, también es un 502 (no se
     * lanza una pregunta rota).
     */
    public function test_si_la_ia_devuelve_un_formato_invalido_devuelve_502(): void
    {
        Exceptions::fake();
        Http::fake([self::GEMINI => Http::response($this->respuestaDeGemini(['texto' => 'Sin opciones']))]);
        $this->empezarPartida();

        $this->lanzarPregunta()->assertStatus(502);

        Event::assertNotDispatched(PreguntaLanzada::class);
        $this->assertSame(0, Pregunta::count());
    }

    /**
     * Si a Gemini se le agotó la cuota (429), el mensaje al host lo dice explícitamente.
     */
    public function test_si_se_agoto_la_cuota_de_la_ia_el_mensaje_lo_avisa(): void
    {
        Exceptions::fake();
        Http::fake([self::GEMINI => Http::response(['error' => ['message' => 'Quota exceeded.']], 429)]);
        $this->empezarPartida();

        $this->lanzarPregunta()
            ->assertStatus(502)
            ->assertJsonPath('message', 'Se agotó la cuota diaria de Gemini (429). Cargá el banco con "php artisan trivia:generar" cuando se renueve.');
    }

    /**
     * Sin haber empezado la partida no se puede lanzar una pregunta (y no se gasta el banco).
     */
    public function test_no_se_puede_lanzar_una_pregunta_sin_empezar_la_partida(): void
    {
        Http::fake();
        $pregunta = $this->crearPreguntaEnBanco();

        $this->lanzarPregunta()
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Primero configurá y empezá la partida.');

        $this->assertNull($pregunta->fresh()->usada_en);
        Event::assertNotDispatched(PreguntaLanzada::class);
    }

    /**
     * Con una pregunta abierta no se puede lanzar otra: la del banco no se desperdicia.
     */
    public function test_no_se_puede_lanzar_otra_pregunta_con_una_abierta(): void
    {
        Http::fake();
        $this->abrirPregunta();
        $otra = $this->crearPreguntaEnBanco(['texto' => '¿Otra pregunta?']);

        $this->lanzarPregunta()
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Todavía hay una pregunta abierta: revelala primero.');

        $this->assertNull($otra->fresh()->usada_en);
        Event::assertDispatched(PreguntaLanzada::class, 1);
    }
}
