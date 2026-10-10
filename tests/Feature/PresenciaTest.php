<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/**
 * Canal de presencia "trivia.presencia": solo pueden suscribirse los jugadores (con su
 * token) y el host (con su sesión con PIN). Lo autoriza POST /broadcasting/auth.
 */
class PresenciaTest extends TriviaTestCase
{
    /** Nombre real del canal: Echo.join('trivia.presencia') le agrega el prefijo "presence-". */
    private const CANAL = 'presence-trivia.presencia';

    protected function setUp(): void
    {
        parent::setUp();

        // En los tests el broadcasting está apagado (BROADCAST_CONNECTION=null) y ese driver
        // autoriza cualquier cosa sin mirar. Para probar la autorización de verdad usamos
        // el driver de Reverb con claves falsas: firmar la respuesta no necesita conexión.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'clave-de-test',
            'broadcasting.connections.reverb.secret' => 'secreto-de-test',
            'broadcasting.connections.reverb.app_id' => 'app-de-test',
        ]);

        // Los canales se registraron en el driver "null" al arrancar: los cargamos en este
        require base_path('routes/channels.php');
    }

    /**
     * Pide autorización para suscribirse al canal, como hace Echo.join() en el navegador.
     *
     * @param  array<string, mixed>  $credenciales  nombre y token del jugador (vacío = host o anónimo)
     */
    private function pedirAcceso(array $credenciales = []): TestResponse
    {
        // En un test todas las peticiones comparten la aplicación y el guard recuerda al
        // último que identificó; en producción cada petición arranca de cero
        Auth::forgetGuards();

        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => self::CANAL,
            ...$credenciales,
        ]);
    }

    /**
     * Datos del miembro que quedan firmados en la respuesta de autorización.
     *
     * @return array{user_id: string, user_info: array{nombre: string, host: bool}}
     */
    private function miembro(TestResponse $respuesta): array
    {
        return json_decode($respuesta->assertOk()->json('channel_data'), true);
    }

    /**
     * Un jugador con su token entra al canal y los demás lo ven con su nombre.
     */
    public function test_un_jugador_con_token_valido_entra_al_canal_con_su_nombre(): void
    {
        $token = $this->unirJugador('Ana');

        $respuesta = $this->pedirAcceso(['nombre' => 'ana', 'token' => $token]);

        $this->assertSame([
            'user_id' => 'jugador:ana',
            'user_info' => ['nombre' => 'Ana', 'host' => false],
        ], $this->miembro($respuesta));
        $this->assertStringStartsWith('clave-de-test:', $respuesta->json('auth'));
    }

    /**
     * Con un token inventado la suscripción se rechaza con 403.
     */
    public function test_la_autorizacion_rechaza_un_token_invalido(): void
    {
        $this->unirJugador('Ana');

        $this->pedirAcceso(['nombre' => 'Ana', 'token' => 'token-inventado'])->assertForbidden();
    }

    /**
     * El token de un jugador no sirve para aparecer como otro.
     */
    public function test_la_autorizacion_rechaza_el_token_de_otro_jugador(): void
    {
        $this->unirJugador('Ana');
        $tokenDeBeto = $this->unirJugador('Beto');

        $this->pedirAcceso(['nombre' => 'Ana', 'token' => $tokenDeBeto])->assertForbidden();
    }

    /**
     * Sin token y sin sesión de host (un curioso) no se puede ver quién está conectado.
     */
    public function test_la_autorizacion_rechaza_a_quien_no_es_jugador_ni_host(): void
    {
        $this->unirJugador('Ana');

        $this->pedirAcceso()->assertForbidden();
    }

    /**
     * Tras reiniciar la partida, el token viejo tampoco sirve para el canal.
     */
    public function test_la_autorizacion_rechaza_un_token_de_antes_de_reiniciar(): void
    {
        $token = $this->unirJugador('Ana');
        $this->comoHost()->postJson('/trivia/reiniciar')->assertOk();
        $this->flushSession();

        $this->pedirAcceso(['nombre' => 'Ana', 'token' => $token])->assertForbidden();
    }

    /**
     * Un jugador quitado por el host pierde el acceso al canal.
     */
    public function test_la_autorizacion_rechaza_a_un_jugador_quitado(): void
    {
        $token = $this->unirJugador('Ana');
        $this->comoHost()->postJson('/trivia/quitar', ['nombre' => 'Ana'])->assertOk();
        $this->flushSession();

        $this->pedirAcceso(['nombre' => 'Ana', 'token' => $token])->assertForbidden();
    }

    /**
     * El host entra con su sesión (PIN), sin token, y figura como host (no como jugador).
     */
    public function test_el_host_entra_al_canal_con_su_sesion(): void
    {
        $respuesta = $this->comoHost()->pedirAcceso();

        $this->assertSame([
            'user_id' => 'host',
            'user_info' => ['nombre' => 'Host', 'host' => true],
        ], $this->miembro($respuesta));
    }

    /**
     * Aunque el navegador tenga la sesión del host, un token inválido no entra como jugador.
     */
    public function test_la_sesion_del_host_no_salva_un_token_invalido(): void
    {
        $this->unirJugador('Ana');

        $this->comoHost()->pedirAcceso(['nombre' => 'Ana', 'token' => 'token-inventado'])->assertForbidden();
    }
}
