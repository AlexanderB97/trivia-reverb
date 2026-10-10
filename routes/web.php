<?php

use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\TriviaController;
use App\Http\Middleware\EnsureTriviaHost;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/emisor'));

Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Trivia en vivo
Route::name('trivia.')->controller(TriviaController::class)->group(function () {
    // Públicas (jugadores)
    Route::get('/jugar', 'jugar')->name('jugar');
    Route::get('/trivia/estado', 'estado')->name('estado');
    Route::post('/trivia/unirse', 'unirse')->middleware('throttle:trivia-jugador')->name('unirse');
    Route::post('/trivia/responder', 'responder')->middleware('throttle:trivia-jugador')->name('responder');
    Route::post('/trivia/reconectar', 'reconectar')->middleware('throttle:trivia-jugador')->name('reconectar');
    // "salir" ya es el nombre de la ruta con la que el host cierra su sesión (/host/salir)
    Route::post('/trivia/salir', 'abandonar')->middleware('throttle:trivia-jugador')->name('abandonar');

    // Login del host (sin el middleware, si no nunca se podría entrar)
    Route::post('/host/login', 'login')->middleware('throttle:trivia-pin')->name('login');

    // Solo el host (sesión autorizada con el PIN)
    Route::middleware(EnsureTriviaHost::class)->group(function () {
        Route::get('/host', 'host')->name('host');
        Route::post('/host/salir', 'salir')->name('salir');
        Route::post('/trivia/empezar', 'empezar')->name('empezar');
        Route::post('/trivia/pregunta', 'pregunta')->middleware('throttle:trivia-ia')->name('pregunta');
        Route::post('/trivia/preparar', 'preparar')->middleware('throttle:trivia-ia')->name('preparar');
        Route::post('/trivia/generar-lote', 'generarLote')->middleware('throttle:trivia-ia')->name('generar-lote');
        Route::post('/trivia/revelar', 'revelar')->name('revelar');
        Route::post('/trivia/reiniciar', 'reiniciar')->name('reiniciar');
        Route::post('/trivia/quitar', 'quitar')->name('quitar');
    });
});
