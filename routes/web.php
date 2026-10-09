<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\TriviaController;

Route::get('/', fn() => redirect('/emisor'));

Route::get('/emisor',   [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir',  [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Trivia en vivo
Route::get('/host',  [TriviaController::class, 'host'])->name('trivia.host');
Route::get('/jugar', [TriviaController::class, 'jugar'])->name('trivia.jugar');

Route::prefix('trivia')->name('trivia.')->controller(TriviaController::class)->group(function () {
    Route::get('/estado',     'estado')->name('estado');
    Route::post('/pregunta',  'pregunta')->name('pregunta');
    Route::post('/responder', 'responder')->name('responder');
    Route::post('/revelar',   'revelar')->name('revelar');
    Route::post('/reiniciar', 'reiniciar')->name('reiniciar');
});