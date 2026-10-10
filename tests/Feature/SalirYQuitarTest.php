<?php

namespace Tests\Feature;

use App\Events\JugadorSalio;
use Illuminate\Support\Facades\Event;

/**
 * Salir y quitar: el jugador puede irse de la partida y el host puede sacar a cualquiera.
 * En los dos casos el nombre queda libre, los puntos desaparecen y se avisa con JugadorSalio.
 */
class SalirYQuitarTest extends TriviaTestCase
{
    /**
     * Al salir, el jugador deja de figurar y otro puede anotarse con el mismo nombre.
     */
    public function test_salir_libera_el_nombre(): void
    {
        $token = $this->unirJugador('Ana');
        $this->unirJugador('Beto');

        $this->postJson('/trivia/salir', ['nombre' => 'Ana', 'token' => $token])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'nombre' => 'Ana', 'cantidad' => 1]);

        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', ['Beto']);

        // El nombre quedó libre: se anota otra persona y recibe un token distinto
        $tokenNuevo = $this->unirJugador('Ana');
        $this->assertNotSame($token, $tokenNuevo);

        // El token viejo ya no sirve para nada
        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $token])->assertUnprocessable();
    }

    /**
     * Los puntos del que sale desaparecen del ranking y los demás suben de puesto.
     * Se avisa con JugadorSalio (quitado = false) y el ranking ya actualizado.
     */
    public function test_al_salir_los_puntos_desaparecen_del_ranking(): void
    {
        $this->abrirPregunta();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');
        $this->responder('Ana', $tokenDeAna, self::CORRECTA)->assertOk();
        $this->responder('Beto', $tokenDeBeto, self::INCORRECTA)->assertOk();
        $this->revelar()->assertOk()->assertJsonPath('ranking.0.nombre', 'Ana');

        $this->postJson('/trivia/salir', ['nombre' => 'Ana', 'token' => $tokenDeAna])->assertOk();

        $rankingSinAna = [['nombre' => 'Beto', 'puntos' => 0, 'ganados' => 0, 'puesto' => 1]];
        $this->getJson('/trivia/estado')->assertJsonPath('ranking', $rankingSinAna);

        Event::assertDispatched(JugadorSalio::class, function (JugadorSalio $evento) use ($rankingSinAna) {
            return $evento->nombre === 'Ana'
                && $evento->cantidad === 1
                && $evento->quitado === false
                && $evento->ranking === $rankingSinAna;
        });
    }

    /**
     * Nadie puede hacer salir a otro: sin el token del jugador se rechaza y no pasa nada.
     */
    public function test_no_se_puede_hacer_salir_a_otro_jugador(): void
    {
        $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        $this->postJson('/trivia/salir', ['nombre' => 'Ana', 'token' => $tokenDeBeto])
            ->assertUnprocessable()
            ->assertJsonPath('reingresar', true);

        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', ['Ana', 'Beto']);
        Event::assertNotDispatched(JugadorSalio::class);
    }

    /**
     * Quitar a un jugador es cosa del host: sin PIN devuelve 403 y el jugador sigue anotado.
     */
    public function test_quitar_a_un_jugador_requiere_el_pin_del_host(): void
    {
        $token = $this->unirJugador('Ana');

        $this->postJson('/trivia/quitar', ['nombre' => 'Ana'])->assertForbidden();

        $this->getJson('/trivia/estado')->assertJsonPath('jugadores', ['Ana']);
        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $token])->assertOk();
        Event::assertNotDispatched(JugadorSalio::class);
    }

    /**
     * El host quita a un jugador: se va de la partida y del ranking, su token deja de
     * valer y JugadorSalio avisa que fue quitado (así su pantalla vuelve a pedir el nombre).
     */
    public function test_el_host_puede_quitar_a_un_jugador(): void
    {
        $token = $this->unirJugador('Ana');
        $this->unirJugador('Beto');

        // El nombre no distingue mayúsculas, igual que al anotarse
        $this->comoHost()->postJson('/trivia/quitar', ['nombre' => 'ANA'])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'nombre' => 'Ana', 'cantidad' => 1]);

        $this->getJson('/trivia/estado')
            ->assertJsonPath('jugadores', ['Beto'])
            ->assertJsonPath('ranking', [['nombre' => 'Beto', 'puntos' => 0, 'ganados' => 0, 'puesto' => 1]]);

        Event::assertDispatched(JugadorSalio::class, function (JugadorSalio $evento) {
            return $evento->nombre === 'Ana' && $evento->quitado === true;
        });

        // El jugador quitado ya no puede responder ni reconectarse con su token
        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $token])->assertUnprocessable();
        $this->responder('Ana', $token, self::CORRECTA)->assertUnprocessable()->assertJsonPath('reingresar', true);
    }

    /**
     * Quitar a alguien que no está (por ejemplo, justo salió solo) devuelve 422 y no avisa nada.
     */
    public function test_quitar_a_un_jugador_que_no_esta_devuelve_422(): void
    {
        $this->comoHost()->postJson('/trivia/quitar', ['nombre' => 'Nadie'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ese jugador ya no está en la partida.');

        Event::assertNotDispatched(JugadorSalio::class);
    }

    /**
     * Si el que sale ya había respondido la pregunta abierta, su respuesta también se
     * borra, y el ranking que viaja en JugadorSalio no muestra los puntos de esa ronda.
     */
    public function test_salir_con_la_pregunta_abierta_no_delata_quien_acerto(): void
    {
        $this->abrirPregunta();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');
        $this->responder('Ana', $tokenDeAna, self::INCORRECTA)->assertOk();
        $this->responder('Beto', $tokenDeBeto, self::CORRECTA)->assertOk();

        $this->postJson('/trivia/salir', ['nombre' => 'Ana', 'token' => $tokenDeAna])->assertOk();

        $this->getJson('/trivia/estado')->assertJsonPath('respondieron', ['Beto']);
        Event::assertDispatched(JugadorSalio::class, function (JugadorSalio $evento) {
            return $evento->ranking === [['nombre' => 'Beto', 'puntos' => 0, 'ganados' => 0, 'puesto' => 1]];
        });
    }
}
