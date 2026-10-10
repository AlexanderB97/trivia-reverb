<?php

namespace Tests\Feature;

use App\Events\JugadorUnido;
use Illuminate\Support\Facades\Event;

/**
 * Reconexión: el navegador guarda el nombre y el token, y al volver a abrir /jugar
 * pregunta con /trivia/reconectar si todavía valen en la partida actual.
 */
class ReconexionJugadorTest extends TriviaTestCase
{
    /**
     * Con el token válido el jugador entra directo: mismo nombre, mismo token y sus
     * puntos, sin anotarse de nuevo (no queda un jugador duplicado ni "huérfano").
     */
    public function test_con_un_token_valido_el_jugador_vuelve_con_su_nombre_y_sus_puntos(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');
        $this->responder('Ana', $token, self::CORRECTA)->assertOk();
        $puntos = $this->revelar()->assertOk()->json('ranking.0.puntos');

        // Cerró la pestaña y volvió: manda lo que tenía guardado (el nombre no distingue mayúsculas)
        $this->postJson('/trivia/reconectar', ['nombre' => 'ana', 'token' => $token])
            ->assertOk()
            ->assertExactJson(['nombre' => 'Ana', 'token' => $token, 'puntos' => $puntos]);

        $this->assertGreaterThanOrEqual(500, $puntos);
        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', ['Ana']);
        Event::assertDispatched(JugadorUnido::class, 1);
    }

    /**
     * Después de "Reiniciar partida" el token guardado ya no vale: 422 con "reingresar"
     * para que el navegador lo borre y pida el nombre. Reconectar no anota a nadie.
     */
    public function test_tras_reiniciar_la_partida_el_token_guardado_ya_no_es_valido(): void
    {
        $token = $this->unirJugador('Ana');
        $this->comoHost()->postJson('/trivia/reiniciar')->assertOk();

        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $token])
            ->assertUnprocessable()
            ->assertJsonPath('reingresar', true);

        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', []);
    }

    /**
     * Con el token de otro jugador (o uno inventado) no se puede entrar con su nombre.
     */
    public function test_no_se_puede_reconectar_con_un_token_ajeno(): void
    {
        $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $tokenDeBeto])
            ->assertUnprocessable()
            ->assertJsonPath('reingresar', true);
    }

    /**
     * Reconectar con una pregunta abierta devuelve los puntos de antes de esa ronda:
     * no sirve para averiguar si la respuesta que se mandó era la correcta.
     */
    public function test_reconectar_con_la_pregunta_abierta_no_delata_si_se_acerto(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');
        $this->responder('Ana', $token, self::CORRECTA)->assertOk();

        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $token])
            ->assertOk()
            ->assertJsonPath('puntos', 0);
    }
}
