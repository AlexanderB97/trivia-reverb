<?php

namespace App\Providers;

use App\Services\Jugadores;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trivia: guard "trivia" para el canal de presencia. Reconoce al jugador por su
        // token o al host por su sesión con PIN (ninguno es un usuario de Laravel)
        Auth::viaRequest('trivia', function (Request $request) {
            return app(Jugadores::class)->miembroDePresencia($request);
        });

        // Trivia: rutas que llaman a Gemini (cuenta por separado cada ruta, por IP)
        RateLimiter::for('trivia-ia', function (Request $request) {
            return Limit::perMinute(10)->by($request->route()?->getName().'|'.$request->ip());
        });

        // Trivia: protección general de TODAS las rutas POST de jugadores, por IP. Es alta
        // a propósito: un aula entera suele salir a internet por la misma IP pública
        // (el wifi de la facultad), así que por IP no se puede ser estricto.
        RateLimiter::for('trivia-ip', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Trivia: anotarse. Todavía no hay token, así que solo se puede limitar por IP
        // (más bajo que el general, pero con lugar para que se anote todo el curso junto)
        RateLimiter::for('trivia-unirse', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Trivia: rutas donde el jugador manda su token (responder, reconectar, salir).
        // El límite es por JUGADOR: que uno abuse no les corta el juego a los demás.
        // Se usa el hash del token para no dejar el token escrito en las claves del cache.
        // Sin token el pedido es inválido de todos modos y lo frena el límite general por IP.
        RateLimiter::for('trivia-jugador', function (Request $request) {
            $token = $request->input('token');

            return is_string($token) && $token !== ''
                ? Limit::perMinute(20)->by('token:'.sha1($token))
                : Limit::none();
        });

        // Trivia: intentos de PIN del host (evita probar PINs por fuerza bruta)
        RateLimiter::for('trivia-pin', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
