<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando un jugador deja la partida: porque tocó "Salir de la partida"
 * o porque el host lo quitó. Su nombre queda libre y sus puntos desaparecen.
 *
 * El host lo usa para sacarlo de la sala y del ranking; el propio jugador, para
 * volver a la pantalla de nombre.
 */
class JugadorSalio implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * El ranking viaja ya sin el jugador. Si hay una pregunta abierta va sin los puntos
     * de esa ronda (igual que en /trivia/estado), para no delatar quién acertó.
     *
     * @param  int  $cantidad  jugadores que quedan anotados
     * @param  bool  $quitado  true si lo quitó el host; false si salió por su cuenta
     * @param  array<int, array{nombre: string, puntos: int, ganados: int, puesto: int}>  $ranking
     */
    public function __construct(
        public string $nombre,
        public int $cantidad,
        public bool $quitado,
        public array $ranking,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('trivia'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'JugadorSalio';
    }
}
