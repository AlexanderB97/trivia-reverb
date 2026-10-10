<?php

namespace Tests\Feature;

/**
 * Límite de pedidos de las rutas de jugadores: estricto por jugador (token) y holgado
 * por IP, porque un aula entera suele salir a internet por la misma IP pública.
 * En los tests todos los pedidos llegan desde la misma IP (127.0.0.1).
 */
class LimiteDePedidosTest extends TriviaTestCase
{
    /**
     * Un curso de 40 personas detrás de la misma IP se anota y responde la misma
     * pregunta en el mismo minuto sin que a nadie le toque un 429.
     */
    public function test_40_jugadores_desde_la_misma_ip_pueden_unirse_y_responder(): void
    {
        $this->abrirPregunta();

        $tokens = [];
        foreach (range(1, 40) as $n) {
            $tokens["Jugador {$n}"] = $this->unirJugador("Jugador {$n}");
        }

        foreach ($tokens as $nombre => $token) {
            $this->responder($nombre, $token, self::CORRECTA)->assertOk();
        }

        $this->getJson('/trivia/estado')
            ->assertJsonCount(40, 'jugadores')
            ->assertJsonCount(40, 'respondieron');
    }

    /**
     * Un mismo token que manda más de 20 pedidos en un minuto recibe 429, y eso no
     * afecta a otro jugador que está en la misma IP.
     */
    public function test_un_mismo_token_que_abusa_recibe_429(): void
    {
        $this->abrirPregunta();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        // Los primeros 20 pasan el límite (el primero responde; el resto da 422 "ya respondiste")
        foreach (range(1, 20) as $intento) {
            $this->assertNotSame(429, $this->responder('Ana', $tokenDeAna, self::CORRECTA)->status());
        }

        $this->responder('Ana', $tokenDeAna, self::CORRECTA)->assertTooManyRequests();

        // El límite es por token y cuenta entre rutas: tampoco puede reconectar...
        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => $tokenDeAna])->assertTooManyRequests();

        // ...pero Beto, desde la misma IP, juega normalmente
        $this->responder('Beto', $tokenDeBeto, self::CORRECTA)->assertOk();
    }

    /**
     * Anotarse (todavía sin token) tiene su propio límite por IP: 120 por minuto.
     */
    public function test_unirse_admite_120_pedidos_por_minuto_desde_la_misma_ip(): void
    {
        foreach (range(1, 120) as $n) {
            $this->unirJugador("Jugador {$n}");
        }

        $this->postJson('/trivia/unirse', ['nombre' => 'Jugador 121'])->assertTooManyRequests();

        $this->getJson('/trivia/estado')->assertJsonCount(120, 'jugadores');
    }

    /**
     * Protección general: aunque alguien cambie de token en cada pedido para esquivar
     * el límite por jugador, desde una misma IP no pasan más de 300 por minuto.
     */
    public function test_la_proteccion_general_por_ip_frena_a_quien_cambia_de_token(): void
    {
        foreach (range(1, 300) as $n) {
            $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => "token-inventado-{$n}"])
                ->assertUnprocessable();
        }

        $this->postJson('/trivia/reconectar', ['nombre' => 'Ana', 'token' => 'token-inventado-301'])
            ->assertTooManyRequests();

        // El límite general también corta las demás rutas de jugadores desde esa IP
        $this->postJson('/trivia/unirse', ['nombre' => 'Ana'])->assertTooManyRequests();
    }
}
