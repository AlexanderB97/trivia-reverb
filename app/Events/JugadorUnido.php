<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando un jugador nuevo se anota con su nombre.
 * El host lo usa para mostrar la sala y saber cuántos jugadores hay.
 */
class JugadorUnido implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $nombre,
        public int $cantidad,
    ) {
    }

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
        return 'JugadorUnido';
    }
}
