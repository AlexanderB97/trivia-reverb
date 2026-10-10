<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Páginas públicas: que la app levante y las pantallas principales respondan.
 */
class ExampleTest extends TestCase
{
    /**
     * La raíz no tiene pantalla propia: redirige al emisor.
     */
    public function test_la_raiz_redirige_al_emisor(): void
    {
        $this->get('/')->assertRedirect('/emisor');
    }

    /**
     * La pantalla de los jugadores es pública y carga sin PIN.
     */
    public function test_la_pantalla_de_jugar_es_publica(): void
    {
        $this->withoutVite();

        $this->get('/jugar')
            ->assertOk()
            ->assertViewIs('trivia.jugar');
    }
}
