<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public string $reason,
        public string $driver,
        public array $realtimePayload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->gameId}")];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    public function broadcastConnections(): array
    {
        return [$this->driver];
    }

    public function broadcastWith(): array
    {
        return $this->realtimePayload;
    }
}
