<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando el host lanza una pregunta nueva.
 * Ojo: NO lleva la respuesta correcta, solo el texto y las opciones.
 */
class PreguntaLanzada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $numero,
        public string $texto,
        public array $opciones,
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
        return 'PreguntaLanzada';
    }
}
