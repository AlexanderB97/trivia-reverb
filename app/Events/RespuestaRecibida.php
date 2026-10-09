<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cada vez que un jugador responde.
 * Solo avisa quién respondió y cuántos van, NO si acertó.
 * "jugadores" es el total de anotados: si cantidad == jugadores, respondieron todos.
 */
class RespuestaRecibida implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $nombre,
        public int $cantidad,
        public int $jugadores,
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
        return 'RespuestaRecibida';
    }
}
