<?php

namespace Tests\Feature;

use App\Events\JugadorUnido;
use App\Events\RespuestaRecibida;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Respuestas de los jugadores: el servidor rechaza con 422 todo lo que no corresponde
 * (sin pregunta abierta, repetida, fuera de tiempo, opción inválida, nombre en uso).
 */
class RespuestasJugadoresTest extends TriviaTestCase
{
    /**
     * Una respuesta válida se acepta y se avisa por evento cuántos respondieron.
     */
    public function test_una_respuesta_valida_se_acepta(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');

        $this->responder('Ana', $token, self::CORRECTA)->assertOk();

        Event::assertDispatched(RespuestaRecibida::class, 1);
        $this->getJson('/trivia/estado')->assertJsonPath('respondieron', ['Ana']);
    }

    /**
     * Sin pregunta abierta (la partida todavía no lanzó ninguna) no se puede responder.
     */
    public function test_responder_sin_pregunta_abierta_devuelve_422(): void
    {
        $token = $this->unirJugador('Ana');

        $this->responder('Ana', $token, self::CORRECTA)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No hay ninguna pregunta abierta en este momento.');

        Event::assertNotDispatched(RespuestaRecibida::class);
    }

    /**
     * Después de revelar, la pregunta queda cerrada y ya no acepta respuestas.
     */
    public function test_responder_una_pregunta_ya_revelada_devuelve_422(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');
        $this->revelar()->assertOk();

        $this->responder('Ana', $token, self::CORRECTA)->assertUnprocessable();

        $this->assertSame(0, $this->filaDelRanking('Ana')['puntos']);
    }

    /**
     * La segunda respuesta a la misma pregunta se rechaza y no cambia el puntaje:
     * no se puede errar primero y "corregir" después.
     */
    public function test_responder_dos_veces_la_misma_pregunta_devuelve_422(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');
        $this->responder('Ana', $token, self::INCORRECTA)->assertOk();

        $this->responder('Ana', $token, self::CORRECTA)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya respondiste esta pregunta.');

        // Los puntos de la ronda se ven recién al revelar
        $this->revelar()->assertOk();
        $this->assertSame(0, $this->filaDelRanking('Ana')['puntos']);
        Event::assertDispatched(RespuestaRecibida::class, 1);
    }

    /**
     * Pasada la hora de cierre el servidor rechaza la respuesta, aunque la pregunta
     * siga abierta (el host todavía no reveló) y el navegador la deje mandar.
     */
    public function test_responder_despues_de_la_hora_de_cierre_devuelve_422(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');

        // Simulamos que el tiempo para responder venció hace 5 segundos
        Cache::forever('trivia.cierra_en', $this->ahoraMs() - 5000);

        $this->responder('Ana', $token, self::CORRECTA)
            ->assertUnprocessable()
            ->assertJsonPath('message', '¡Se terminó el tiempo para responder!');

        $this->revelar()->assertOk();
        $this->assertSame(0, $this->filaDelRanking('Ana')['puntos']);
    }

    /**
     * Las opciones válidas son 0, 1, 2 y 3: cualquier otra cosa falla la validación.
     */
    #[DataProvider('opcionesInvalidas')]
    public function test_una_opcion_fuera_de_0_a_3_devuelve_422(mixed $opcion): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');

        $this->postJson('/trivia/responder', ['nombre' => 'Ana', 'token' => $token, 'opcion' => $opcion])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('opcion');

        Event::assertNotDispatched(RespuestaRecibida::class);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function opcionesInvalidas(): array
    {
        return [
            'mayor a 3' => [4],
            'negativa' => [-1],
            'no es un número' => ['a'],
            'vacía' => [null],
        ];
    }

    /**
     * Nadie puede responder en nombre de otro: sin el token del jugador se rechaza.
     */
    public function test_responder_con_un_token_ajeno_devuelve_422(): void
    {
        $this->abrirPregunta();
        $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        $this->responder('Ana', $tokenDeBeto, self::CORRECTA)
            ->assertUnprocessable()
            ->assertJsonPath('reingresar', true);

        $this->revelar()->assertOk();
        $this->assertSame(0, $this->filaDelRanking('Ana')['puntos']);
    }

    /**
     * Los nombres son únicos sin distinguir mayúsculas: "ANA" choca con "Ana".
     */
    public function test_un_nombre_repetido_con_otras_mayusculas_se_rechaza_al_unirse(): void
    {
        $this->unirJugador('Ana');

        $this->postJson('/trivia/unirse', ['nombre' => 'ANA'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El nombre "Ana" ya está en uso. Elegí otro.');

        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', ['Ana']);
        Event::assertDispatched(JugadorUnido::class, 1);
    }

    /**
     * El mismo jugador sí puede volver a entrar (por ejemplo al recargar) si manda su token.
     */
    public function test_el_mismo_jugador_puede_volver_a_entrar_con_su_token(): void
    {
        $token = $this->unirJugador('Ana');

        $this->postJson('/trivia/unirse', ['nombre' => 'ana', 'token' => $token])
            ->assertOk()
            ->assertExactJson(['nombre' => 'Ana', 'token' => $token]);
    }
}
