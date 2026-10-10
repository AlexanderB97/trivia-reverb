<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTriviaHost;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Seguridad del host: a /host y a las rutas que manejan la partida solo se entra con el PIN.
 */
class SeguridadHostTest extends TriviaTestCase
{
    /**
     * Sin PIN, /host no muestra la pantalla del host sino el formulario para ingresarlo.
     */
    public function test_host_sin_pin_muestra_el_formulario_de_pin(): void
    {
        $this->get('/host')
            ->assertForbidden()
            ->assertViewIs('trivia.pin')
            ->assertSee('name="pin"', false);
    }

    /**
     * Con el PIN correcto la sesión queda autorizada y /host muestra la pantalla del host.
     */
    public function test_con_el_pin_correcto_se_entra_al_host(): void
    {
        $this->post('/host/login', ['pin' => self::PIN])
            ->assertRedirect(route('trivia.host'))
            ->assertSessionHas(EnsureTriviaHost::SESION, true);

        $this->get('/host')
            ->assertOk()
            ->assertViewIs('trivia.host');
    }

    /**
     * Con un PIN incorrecto no se autoriza la sesión y /host sigue pidiendo el PIN.
     */
    public function test_un_pin_incorrecto_no_da_acceso(): void
    {
        $this->post('/host/login', ['pin' => '000000'])
            ->assertRedirect(route('trivia.host'))
            ->assertSessionHas('pin_error', 'PIN incorrecto.')
            ->assertSessionMissing(EnsureTriviaHost::SESION);

        $this->get('/host')
            ->assertForbidden()
            ->assertViewIs('trivia.pin')
            ->assertSee('PIN incorrecto.');
    }

    /**
     * Si TRIVIA_HOST_PIN está vacío nadie puede entrar (ni siquiera mandando un PIN cualquiera).
     */
    public function test_sin_pin_configurado_nadie_puede_entrar(): void
    {
        config(['trivia.host_pin' => '']);

        $this->post('/host/login', ['pin' => self::PIN])
            ->assertRedirect(route('trivia.host'))
            ->assertSessionMissing(EnsureTriviaHost::SESION);

        $this->get('/host')->assertForbidden();
    }

    /**
     * El login tiene límite de intentos: después de 5 PIN en un minuto responde 429,
     * así no se puede adivinar el PIN probando uno tras otro.
     */
    public function test_el_login_bloquea_despues_de_varios_intentos(): void
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $this->post('/host/login', ['pin' => '000000'])->assertRedirect();
        }

        // Ya bloqueado: ni el PIN correcto entra hasta que pase el minuto
        $this->post('/host/login', ['pin' => self::PIN])->assertTooManyRequests();
        $this->get('/host')->assertForbidden();
    }

    /**
     * Al salir, la sesión deja de ser host y /host vuelve a pedir el PIN.
     */
    public function test_al_salir_se_pierde_el_acceso_de_host(): void
    {
        $this->comoHost()->post('/host/salir')->assertRedirect(route('trivia.host'));

        $this->get('/host')->assertForbidden();
    }

    /**
     * Todas las rutas POST del host devuelven 403 sin PIN y no emiten ningún evento.
     */
    #[DataProvider('rutasPostDelHost')]
    public function test_las_rutas_post_del_host_devuelven_403_sin_pin(string $ruta): void
    {
        $this->crearPreguntaEnBanco();

        $this->post($ruta)
            ->assertForbidden()
            ->assertJsonPath('message', 'No autorizado: ingresá el PIN del host en /host.');

        Event::assertNothingDispatched();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rutasPostDelHost(): array
    {
        return [
            'salir' => ['/host/salir'],
            'empezar' => ['/trivia/empezar'],
            'pregunta' => ['/trivia/pregunta'],
            'preparar' => ['/trivia/preparar'],
            'generar lote' => ['/trivia/generar-lote'],
            'revelar' => ['/trivia/revelar'],
            'reiniciar' => ['/trivia/reiniciar'],
            'quitar' => ['/trivia/quitar'],
        ];
    }
}
