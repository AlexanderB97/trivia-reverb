<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Páginas de error personalizadas (resources/views/errors): tienen el diseño de la
 * trivia y no dependen del build de Vite, para verse bien incluso durante un deploy.
 *
 * A propósito este test NO usa withoutVite() en las páginas de error: si alguna vista
 * usara la directiva de Vite, acá aparecerían los archivos compilados (o fallaría).
 */
class PaginasErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ruta solo de los tests para provocar cualquier error: /_error/429 hace abort(429)
        Route::get('/_error/{codigo}', fn (int $codigo) => abort($codigo));
    }

    /**
     * /host sin PIN devuelve 403 y, junto al formulario de PIN, explica que la pantalla
     * es solo para el host y le ofrece al jugador perdido el camino a /jugar.
     */
    public function test_host_sin_pin_devuelve_403_con_el_texto_personalizado(): void
    {
        $this->withoutVite();

        $this->get('/host')
            ->assertForbidden()
            ->assertSee('Esta pantalla es solo para el host')
            ->assertSee('Ir a jugar')
            ->assertSee('href="'.route('trivia.jugar').'"', false)
            // El host tiene que poder seguir entrando: el formulario de PIN sigue ahí
            ->assertSee('name="pin"', false);
    }

    /**
     * Una ruta que no existe devuelve 404 con la página de la trivia y el botón para ir a jugar.
     */
    public function test_una_ruta_inexistente_devuelve_404_con_el_texto_personalizado(): void
    {
        $this->get('/esta-pagina-no-existe')
            ->assertNotFound()
            ->assertSee('¡Pregunta equivocada!')
            ->assertSee('Ir a jugar')
            ->assertSee('href="/jugar"', false);
    }

    /**
     * Cada código de error muestra su página: el número, su título y su botón.
     */
    #[DataProvider('paginasDeError')]
    public function test_cada_error_muestra_su_pagina_personalizada(int $codigo, string $titulo, string $boton): void
    {
        $respuesta = $this->get("/_error/{$codigo}")
            ->assertStatus($codigo)
            ->assertSee($titulo)
            ->assertSee($boton)
            ->assertSee('Trivia')
            ->assertSee('aria-label="Error '.$codigo.'"', false);

        // Ya renderizada, no carga nada compilado ni externo
        $respuesta->assertDontSee('build/assets', false)
            ->assertDontSee('<link', false)
            ->assertDontSee('src=', false);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function paginasDeError(): array
    {
        return [
            '403 solo para el host' => [403, '¡Zona del host!', 'Ir a jugar'],
            '404 no existe' => [404, '¡Pregunta equivocada!', 'Ir a jugar'],
            '419 sesión vencida' => [419, '¡Se acabó el tiempo!', 'Recargar'],
            '429 demasiados intentos' => [429, '¡Pará un poco!', 'Volver a intentar'],
            '500 error nuestro' => [500, '¡Esta la erramos nosotros!', 'Ir a jugar'],
            '503 actualizando' => [503, '¡Entretiempo!', 'Probar ahora'],
        ];
    }

    /**
     * La 403 manda al jugador a /jugar y le deja al host el link a /host (donde está el PIN).
     */
    public function test_la_403_ofrece_ir_a_jugar_y_el_link_para_el_host(): void
    {
        $this->get('/_error/403')
            ->assertSee('href="/jugar"', false)
            ->assertSee('Soy el host')
            ->assertSee('href="/host"', false);
    }

    /**
     * La 503 se recarga sola cada 15 segundos y muestra la animación de espera.
     */
    public function test_la_503_se_recarga_sola_y_tiene_animacion(): void
    {
        $this->get('/_error/503')
            ->assertSee('<meta http-equiv="refresh" content="15">', false)
            ->assertSee('Estamos actualizando la trivia, volvé en unos segundos')
            ->assertSee('class="puntos"', false)
            ->assertSee('@keyframes rebote', false);
    }

    /**
     * Las vistas de error no usan Vite ni archivos compilados: todo el CSS va adentro
     * del layout, así se ven bien aunque el build falle o haya un deploy en curso.
     */
    public function test_las_vistas_de_error_no_dependen_del_build(): void
    {
        $vistas = File::files(resource_path('views/errors'));

        // El layout y las seis páginas
        $this->assertCount(7, $vistas);

        foreach ($vistas as $vista) {
            $contenido = $vista->getContents();

            $this->assertStringNotContainsString('@vite', $contenido, $vista->getFilename().' usa @vite.');
            $this->assertStringNotContainsString('build/assets', $contenido, $vista->getFilename().' apunta a archivos compilados.');
        }

        $this->assertStringContainsString('<style>', File::get(resource_path('views/errors/layout.blade.php')));
    }

    /**
     * Los pedidos con fetch (JSON) siguen recibiendo JSON, no la página HTML: así
     * /jugar y /host pueden mostrar su propio aviso.
     */
    public function test_los_pedidos_json_siguen_recibiendo_json(): void
    {
        $this->getJson('/_error/429')
            ->assertTooManyRequests()
            ->assertJsonStructure(['message'])
            ->assertDontSee('¡Pará un poco!');
    }
}
