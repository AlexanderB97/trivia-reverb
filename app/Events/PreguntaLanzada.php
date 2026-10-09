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
 *
 * Los tiempos van en milisegundos (timestamp). Se manda también "ahora" (la hora del
 * servidor al emitir) para que cada navegador calcule cuánto falta aunque su reloj
 * esté adelantado o atrasado: restante = cierraEn - ahora.
 */
class PreguntaLanzada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $numero,
        public int $total,
        public string $texto,
        public array $opciones,
        public int $segundos,
        public int $cierraEn,
        public int $ahora,
        public ?string $tema = null, // tema de la materia (null si no hay temas configurados)
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
