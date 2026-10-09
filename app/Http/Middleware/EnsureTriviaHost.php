<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo si en la sesión quedó guardado que este navegador es el host
 * (eso se guarda al ingresar el PIN correcto en TriviaController::login()).
 */
class EnsureTriviaHost
{
    /** Clave de sesión que marca al host autorizado. */
    public const SESION = 'trivia.host';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(self::SESION) === true) {
            return $next($request);
        }

        // Llamadas con fetch (las rutas POST del host): 403 en JSON
        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json([
                'message' => 'No autorizado: ingresá el PIN del host en /host.',
            ], 403);
        }

        // Navegador entrando a /host: mostramos el formulario de PIN
        return response()->view('trivia.pin', [
            'pinConfigurado' => (string) config('trivia.host_pin') !== '',
        ], 403);
    }
}
