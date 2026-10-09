<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemoteStyleUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $newText;
    public string $newStyle;
    /**
     * Create a new event instance.
     */
      public function __construct(string $newText, string $newStyle)
    {
        $this->newText = $newText;
        $this->newStyle = $newStyle;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
              return [
            new Channel('style-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'RemoteStyleUpdate';
    }
}
