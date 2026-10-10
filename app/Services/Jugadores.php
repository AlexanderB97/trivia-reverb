<?php

namespace App\Services;

use App\Http\Middleware\EnsureTriviaHost;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Identifica a los jugadores de la trivia. No son usuarios de Laravel: cada uno se
 * reconoce por su nombre y el token secreto que recibió al anotarse.
 */
class Jugadores
{
    /** Clave del Cache: [nombre en minúsculas => ['nombre' => ..., 'token' => ...]]. */
    public const CLAVE = 'trivia.jugadores';

    /**
     * Devuelve el jugador anotado con ese nombre si el token es el suyo, o null.
     * El nombre no distingue mayúsculas; el token se compara en tiempo constante.
     *
     * @return array{nombre: string, token: string}|null
     */
    public function conToken(string $nombre, string $token): ?array
    {
        $jugador = Cache::get(self::CLAVE, [])[mb_strtolower(trim($nombre))] ?? null;

        if (! $jugador || $token === '' || ! hash_equals($jugador['token'], $token)) {
            return null;
        }

        return $jugador;
    }

    /**
     * Quién pide entrar al canal de presencia (lo usa el guard "trivia"):
     *  - un jugador, si manda su nombre y su token válidos en la partida actual;
     *  - el host, si no manda token y su sesión está autorizada con el PIN;
     *  - nadie (null) en cualquier otro caso: la suscripción se rechaza con 403.
     *
     * El "id" identifica al miembro en el canal: dos pestañas del mismo jugador cuentan
     * como un solo conectado.
     */
    public function miembroDePresencia(Request $request): ?GenericUser
    {
        $nombre = $request->input('nombre');
        $token = $request->input('token');

        // Si manda token se lo trata como jugador, aunque el navegador también sea el del host
        if (is_string($token) && $token !== '') {
            $jugador = is_string($nombre) ? $this->conToken($nombre, $token) : null;

            return $jugador ? new GenericUser([
                'id' => 'jugador:'.mb_strtolower($jugador['nombre']),
                'nombre' => $jugador['nombre'],
                'host' => false,
            ]) : null;
        }

        if ($request->hasSession() && $request->session()->get(EnsureTriviaHost::SESION) === true) {
            return new GenericUser(['id' => 'host', 'nombre' => 'Host', 'host' => true]);
        }

        return null;
    }
}
