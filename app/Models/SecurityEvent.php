<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $fillable = [
        'title',
        'event_type',
        'severity',
        'description',
        'user_id',
        'source_ip',
        'metadata',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $title,
        string $eventType,
        string $severity,
        ?User $user = null,
        ?string $sourceIp = null,
        ?array $metadata = null,
        ?string $description = null,
    ): self {
        return static::query()->create([
            'title' => $title,
            'event_type' => $eventType,
            'severity' => $severity,
            'description' => $description ?? $title,
            'user_id' => $user?->id,
            'source_ip' => $sourceIp,
            'metadata' => $metadata ?? [],
            'status' => 'new',
            'occurred_at' => now(),
        ]);
    }
}
