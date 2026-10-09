<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando el host revela el resultado de la ronda.
 * Recién acá viaja la respuesta correcta, junto con la explicación y el ranking.
 */
class ResultadoRevelado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{nombre: string, puntos: int, ganados: int, puesto: int}>  $ranking
     *         ordenado de mayor a menor; "ganados" son los puntos de esta pregunta y
     *         "puesto" ya contempla empates (mismo puntaje = mismo puesto)
     * @param  bool  $final  true si era la última pregunta de la partida
     */
    public function __construct(
        public int $correcta,
        public string $explicacion,
        public array $ranking,
        public int $numero,
        public int $total,
        public bool $final,
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
        return 'ResultadoRevelado';
    }
}
