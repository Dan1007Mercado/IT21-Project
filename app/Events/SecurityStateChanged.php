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
        // The event itself is dispatched after commit. Local uses the sync
        // queue for deterministic diagnostics; production can select a
        // supervised durable queue without changing event semantics.
        $this->connection = (string) config('intsec.realtime_queue_connection', 'database');
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
            'record' => $this->sanitize($this->record),
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $values */
    private function sanitize(array $values): array
    {
        $sensitive = '/(^|[_-])(password|passwd|secret|token|authorization|cookie|csrf|session|credential|api[_-]?key)([_-]|$)/i';

        foreach ($values as $key => $value) {
            if (preg_match($sensitive, (string) $key) === 1) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
