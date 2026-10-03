<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SecurityStateChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 10, 30, 60];

    /** @param array<string, mixed> $record */
    public function __construct(
        public string $entity,
        public string $action,
        public ?string $source,
        public array $record,
    ) {
        // A failed WebSocket server must never fail the database write/request.
        // Production broadcasts are handled by the durable database queue.
        $this->connection = 'database';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('intsec.security')];
    }

    public function broadcastAs(): string
    {
        return 'security.state.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'entity' => $this->entity,
            'action' => $this->action,
            'source' => $this->source,
            'record' => $this->record,
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }
}
