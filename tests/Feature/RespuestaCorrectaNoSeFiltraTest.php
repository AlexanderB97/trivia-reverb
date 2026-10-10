<?php

namespace Tests\Feature;

use App\Events\PreguntaLanzada;
use App\Events\RespuestaRecibida;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;

/**
 * La respuesta correcta nunca sale del servidor mientras la pregunta está abierta:
 * ni en el evento que reciben los jugadores, ni en /trivia/estado, ni en lo que ve el host.
 */
class RespuestaCorrectaNoSeFiltraTest extends TriviaTestCase
{
    /**
     * El evento PreguntaLanzada lleva el texto y las opciones, pero no la correcta ni la explicación.
     */
    public function test_el_evento_pregunta_lanzada_no_contiene_la_respuesta_correcta(): void
    {
        $pregunta = $this->abrirPregunta();

        Event::assertDispatched(PreguntaLanzada::class, function (PreguntaLanzada $evento) use ($pregunta) {
            // Lo que se transmite son las propiedades públicas del evento
            $transmitido = get_object_vars($evento);

            $this->assertSame($pregunta->texto, $transmitido['texto']);
            $this->assertSame($pregunta->opciones, $transmitido['opciones']);
            $this->assertSinRespuestaCorrecta($transmitido);

            return true;
        });
    }

    /**
     * /trivia/estado muestra la pregunta abierta (texto, opciones y tema) sin la correcta,
     * incluso para un jugador que ya respondió.
     */
    public function test_el_estado_no_devuelve_la_respuesta_correcta_con_la_pregunta_abierta(): void
    {
        $pregunta = $this->abrirPregunta();
        $token = $this->unirJugador('Ana');
        $this->responder('Ana', $token, self::CORRECTA)->assertOk();

        $estado = $this->getJson('/trivia/estado?nombre=Ana')
            ->assertOk()
            ->assertJsonPath('abierta', true)
            ->assertJsonPath('miOpcion', self::CORRECTA)
            ->assertJsonPath('pregunta.texto', $pregunta->texto);

        // De la pregunta solo viajan estos tres datos
        $this->assertSame(['texto', 'opciones', 'tema'], array_keys($estado->json('pregunta')));
        $this->assertSinRespuestaCorrecta($estado->json());
    }

    /**
     * La respuesta que recibe el host al lanzar la pregunta tampoco incluye la correcta.
     */
    public function test_la_respuesta_al_host_al_lanzar_la_pregunta_no_incluye_la_correcta(): void
    {
        $pregunta = $this->crearPreguntaEnBanco();
        $this->empezarPartida();

        $respuesta = $this->lanzarPregunta()
            ->assertOk()
            ->assertJsonPath('texto', $pregunta->texto)
            ->assertJsonPath('opciones', $pregunta->opciones);

        $this->assertSinRespuestaCorrecta($respuesta->json());
    }

    /**
     * Reproduce el ataque de "probar opciones": cuatro jugadores responden una opción
     * distinta cada uno y miran /trivia/estado para ver quién sumó puntos. Antes de
     * revelar, las cuatro filas del ranking tienen que ser indistinguibles.
     */
    public function test_el_estado_no_permite_distinguir_quien_acerto_antes_de_revelar(): void
    {
        $this->abrirPregunta();
        $jugadores = ['Opcion0', 'Opcion1', 'Opcion2', 'Opcion3'];

        foreach ($jugadores as $opcion => $nombre) {
            $this->responder($nombre, $this->unirJugador($nombre), $opcion)->assertOk();
        }

        // Lo mira cada uno de los cuatro (con y sin su nombre en la consulta)
        foreach ([...$jugadores, ''] as $nombre) {
            $ranking = $this->getJson('/trivia/estado?nombre='.$nombre)->assertOk()->json('ranking');

            $this->assertCount(4, $ranking);
            foreach ($ranking as $fila) {
                $this->assertSame(
                    ['puntos' => 0, 'ganados' => 0, 'puesto' => 1],
                    ['puntos' => $fila['puntos'], 'ganados' => $fila['ganados'], 'puesto' => $fila['puesto']],
                    "La fila de {$fila['nombre']} delata si acertó antes de revelar.",
                );
            }
        }

        // Recién al revelar aparecen los puntos: solo sumó el que eligió la correcta
        $this->revelar()->assertOk();

        $this->assertGreaterThanOrEqual(500, $this->filaDelRanking($jugadores[self::CORRECTA])['puntos']);
        $this->assertSame(0, $this->filaDelRanking($jugadores[self::INCORRECTA])['puntos']);
    }

    /**
     * Con puntos de rondas anteriores pasa lo mismo: mientras la pregunta está abierta
     * el ranking es el de ANTES de esa pregunta, aunque ya hayan respondido.
     */
    public function test_el_estado_muestra_el_ranking_de_antes_de_la_pregunta_abierta(): void
    {
        $this->crearPreguntaEnBanco();
        $this->crearPreguntaEnBanco(['texto' => '¿Segunda pregunta?']);
        $this->empezarPartida();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        // Primera ronda: acierta Ana
        $this->lanzarPregunta()->assertOk();
        $this->responder('Ana', $tokenDeAna, self::CORRECTA)->assertOk();
        $this->responder('Beto', $tokenDeBeto, self::INCORRECTA)->assertOk();
        $rankingRevelado = $this->revelar()->assertOk()->json('ranking');

        // Segunda ronda: ahora acierta Beto, pero todavía no se reveló
        $this->lanzarPregunta()->assertOk();
        $this->responder('Ana', $tokenDeAna, self::INCORRECTA)->assertOk();
        $this->responder('Beto', $tokenDeBeto, self::CORRECTA)->assertOk();

        $this->getJson('/trivia/estado?nombre=Beto')
            ->assertOk()
            ->assertJsonPath('abierta', true)
            ->assertJsonPath('ranking', [
                ['nombre' => 'Ana', 'puntos' => $rankingRevelado[0]['puntos'], 'ganados' => 0, 'puesto' => 1],
                ['nombre' => 'Beto', 'puntos' => 0, 'ganados' => 0, 'puesto' => 2],
            ]);

        // Al revelar sí se suman los puntos de Beto
        $this->revelar()->assertOk();
        $this->assertGreaterThanOrEqual(500, $this->filaDelRanking('Beto')['puntos']);
    }

    /**
     * Ni la respuesta de /trivia/responder ni el evento RespuestaRecibida cambian según
     * se acierte o se erre: solo dicen quién respondió y cuántos van.
     */
    public function test_responder_y_respuesta_recibida_no_dependen_de_si_se_acerto(): void
    {
        $this->abrirPregunta();
        $tokenDeAna = $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        // Ana acierta y Beto erra: las dos respuestas solo difieren en el contador
        $this->responder('Ana', $tokenDeAna, self::CORRECTA)
            ->assertOk()
            ->assertExactJson(['ok' => true, 'cantidad' => 1]);
        $this->responder('Beto', $tokenDeBeto, self::INCORRECTA)
            ->assertOk()
            ->assertExactJson(['ok' => true, 'cantidad' => 2]);

        // Lo que se transmite son las propiedades públicas del evento ("socket" es de Laravel)
        $transmitidos = Event::dispatched(RespuestaRecibida::class)
            ->map(fn (array $llamada) => Arr::except(get_object_vars($llamada[0]), 'socket'))
            ->all();

        $this->assertSame([
            ['nombre' => 'Ana', 'cantidad' => 1, 'jugadores' => 2],
            ['nombre' => 'Beto', 'cantidad' => 2, 'jugadores' => 2],
        ], $transmitidos);
    }

    /**
     * Falla si en los datos aparece una clave "correcta" o "explicacion" (a cualquier
     * profundidad) o el texto de la explicación.
     *
     * @param  array<string, mixed>  $datos
     */
    private function assertSinRespuestaCorrecta(array $datos): void
    {
        $claves = $this->clavesDe($datos);

        $this->assertNotContains('correcta', $claves, 'Se filtró la clave "correcta".');
        $this->assertNotContains('explicacion', $claves, 'Se filtró la clave "explicacion".');
        $this->assertStringNotContainsString('EXPLICACION-SECRETA', json_encode($datos), 'Se filtró el texto de la explicación.');
    }

    /**
     * Todas las claves de un array, incluidas las de los arrays que tiene adentro.
     *
     * @param  array<int|string, mixed>  $datos
     * @return array<int, int|string>
     */
    private function clavesDe(array $datos): array
    {
        $claves = [];

        foreach ($datos as $clave => $valor) {
            $claves[] = $clave;

            if (is_array($valor)) {
                $claves = [...$claves, ...$this->clavesDe($valor)];
            }
        }

        return $claves;
    }
}
