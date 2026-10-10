<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presencia de la trivia: quién está conectado en este momento.
// El guard "trivia" (AppServiceProvider) identifica al que se suscribe por su token de
// jugador o por la sesión del host; si no reconoce a nadie, Laravel responde 403 y este
// callback ni se ejecuta. Lo que devuelve son los datos que ven los demás miembros.
Broadcast::channel('trivia.presencia', function (GenericUser $miembro): array {
    return ['nombre' => $miembro->nombre, 'host' => $miembro->host];
}, ['guards' => ['trivia']]);
