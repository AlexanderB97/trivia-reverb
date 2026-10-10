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

        // Trivia: respuestas y altas de jugadores, por IP
        RateLimiter::for('trivia-jugador', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Trivia: intentos de PIN del host (evita probar PINs por fuerza bruta)
        RateLimiter::for('trivia-pin', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
