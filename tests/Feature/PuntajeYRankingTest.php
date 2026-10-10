<?php

namespace Tests\Feature;

use App\Events\ResultadoRevelado;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Puntaje y ranking: puntos por velocidad (1000 al instante, 500 al final, 0 si erró),
 * puestos con empates y lo que se transmite al revelar.
 */
class PuntajeYRankingTest extends TriviaTestCase
{
    /**
     * Una respuesta correcta suma entre 500 y 1000 puntos; respondiendo enseguida, casi 1000.
     */
    public function test_una_respuesta_correcta_suma_entre_500_y_1000_puntos(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');

        $this->responder('Ana', $token, self::CORRECTA)->assertOk();
        $this->revelar()->assertOk();

        $fila = $this->filaDelRanking('Ana');
        $this->assertGreaterThanOrEqual(500, $fila['puntos']);
        $this->assertLessThanOrEqual(1000, $fila['puntos']);
        $this->assertGreaterThan(900, $fila['puntos'], 'Respondiendo al instante deberían ser casi 1000 puntos.');
        $this->assertSame($fila['puntos'], $fila['ganados']);
    }

    /**
     * Cuanto más se tarda, menos puntos: respondiendo sobre el final quedan cerca de 500
     * (nunca menos).
     */
    public function test_una_respuesta_correcta_sobre_el_final_suma_cerca_de_500_puntos(): void
    {
        $this->abrirPregunta(segundos: 20);
        $token = $this->unirJugador('Ana');

        // Simulamos que la pregunta se lanzó hace 19 de los 20 segundos
        $lanzadaEn = $this->ahoraMs() - 19000;
        Cache::forever('trivia.lanzada_en', $lanzadaEn);
        Cache::forever('trivia.cierra_en', $lanzadaEn + 20000);

        $this->responder('Ana', $token, self::CORRECTA)->assertOk();
        $this->revelar()->assertOk();

        $puntos = $this->filaDelRanking('Ana')['puntos'];
        $this->assertGreaterThanOrEqual(500, $puntos);
        $this->assertLessThan(600, $puntos);
    }

    /**
     * Una respuesta incorrecta suma 0.
     */
    public function test_una_respuesta_incorrecta_suma_0_puntos(): void
    {
        $this->abrirPregunta();
        $token = $this->unirJugador('Ana');

        $this->responder('Ana', $token, self::INCORRECTA)->assertOk();
        $this->revelar()->assertOk();

        $fila = $this->filaDelRanking('Ana');
        $this->assertSame(0, $fila['puntos']);
        $this->assertSame(0, $fila['ganados']);
    }

    /**
     * Los empates comparten puesto y el siguiente salta: 900, 900, 700 → puestos 1, 1, 3.
     */
    public function test_los_empates_comparten_puesto(): void
    {
        // Puntajes acumulados de la partida, cargados desordenados a propósito
        Cache::forever('trivia.puntajes', ['Caro' => 700, 'Beto' => 900, 'Ana' => 900]);

        $this->getJson('/trivia/estado')
            ->assertOk()
            ->assertJsonPath('ranking', [
                ['nombre' => 'Ana', 'puntos' => 900, 'ganados' => 0, 'puesto' => 1],
                ['nombre' => 'Beto', 'puntos' => 900, 'ganados' => 0, 'puesto' => 1],
                ['nombre' => 'Caro', 'puntos' => 700, 'ganados' => 0, 'puesto' => 3],
            ]);
    }

    /**
     * Al revelar se transmite ResultadoRevelado con la correcta, la explicación y el
     * ranking con puesto; el host recibe lo mismo y la pregunta queda cerrada.
     */
    public function test_resultado_revelado_incluye_correcta_explicacion_y_ranking_con_puesto(): void
    {
        $this->abrirPregunta();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');
        $this->responder('Ana', $tokenDeAna, self::CORRECTA)->assertOk();
        $this->responder('Beto', $tokenDeBeto, self::INCORRECTA)->assertOk();

        $respuesta = $this->revelar()->assertOk();

        // Los puntos de Ana dependen de la velocidad: los tomamos de la respuesta
        // (el rango 500-1000 lo verifican los tests de arriba)
        $puntosDeAna = $respuesta->json('ranking.0.puntos');
        $this->assertGreaterThanOrEqual(500, $puntosDeAna);

        $rankingEsperado = [
            ['nombre' => 'Ana', 'puntos' => $puntosDeAna, 'ganados' => $puntosDeAna, 'puesto' => 1],
            ['nombre' => 'Beto', 'puntos' => 0, 'ganados' => 0, 'puesto' => 2],
        ];

        $respuesta
            ->assertExactJson([
                'correcta' => self::CORRECTA,
                'explicacion' => self::EXPLICACION,
                'ranking' => $rankingEsperado,
                'numero' => 1,
                'total' => 3,
                'final' => false,
            ]);

        Event::assertDispatched(ResultadoRevelado::class, function (ResultadoRevelado $evento) use ($rankingEsperado) {
            return $evento->correcta === self::CORRECTA
                && $evento->explicacion === self::EXPLICACION
                && $evento->ranking === $rankingEsperado
                && $evento->numero === 1
                && $evento->total === 3
                && $evento->final === false;
        });

        // Ya revelado, el estado muestra el mismo ranking (para el que recarga la página)
        $this->getJson('/trivia/estado')
            ->assertJsonPath('abierta', false)
            ->assertJsonPath('pregunta', null)
            ->assertJsonPath('ranking', $rankingEsperado);
    }

    /**
     * Los puntos se acumulan de una pregunta a la otra: el total es la suma de las rondas
     * y "ganados" solo lo de la última.
     */
    public function test_los_puntos_se_acumulan_entre_preguntas(): void
    {
        $this->crearPreguntaEnBanco();
        $this->crearPreguntaEnBanco(['texto' => '¿Segunda pregunta?']);
        $this->empezarPartida();
        $token = $this->unirJugador('Ana');

        $this->lanzarPregunta()->assertOk();
        $this->responder('Ana', $token, self::CORRECTA)->assertOk();
        $primera = $this->revelar()->assertOk()->json('ranking.0');

        $this->lanzarPregunta()->assertOk();
        $this->responder('Ana', $token, self::CORRECTA)->assertOk();
        $segunda = $this->revelar()->assertOk()->json('ranking.0');

        $this->assertGreaterThanOrEqual(500, $segunda['ganados']);
        $this->assertSame($primera['puntos'] + $segunda['ganados'], $segunda['puntos']);
    }

    /**
     * En la última pregunta de la partida, ResultadoRevelado avisa que es el final.
     */
    public function test_resultado_revelado_avisa_cuando_es_la_ultima_pregunta(): void
    {
        $this->crearPreguntaEnBanco();
        $this->empezarPartida(total: 1);
        $this->lanzarPregunta()->assertOk();

        $this->revelar()->assertOk()->assertJsonPath('final', true);

        Event::assertDispatched(ResultadoRevelado::class, fn (ResultadoRevelado $evento) => $evento->final === true);
        $this->getJson('/trivia/estado')->assertJsonPath('terminada', true);
    }

    /**
     * Sin pregunta abierta no hay nada que revelar: 422 y no se transmite nada.
     */
    public function test_revelar_sin_pregunta_abierta_devuelve_422(): void
    {
        $this->revelar()->assertUnprocessable();

        Event::assertNotDispatched(ResultadoRevelado::class);
    }
}
