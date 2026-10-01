<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $fillable = [
        'title',
        'source',
        'external_event_id',
        'event_type',
        'rule_key',
        'severity',
        'risk_score',
        'confidence',
        'description',
        'user_id',
        'request_activity_id',
        'authentication_log_id',
        'source_ip',
        'metadata',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'risk_score' => 'integer',
        'confidence' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestActivity(): BelongsTo
    {
        return $this->belongsTo(RequestActivity::class);
    }

    public function authenticationLog(): BelongsTo
    {
        return $this->belongsTo(AuthenticationLog::class);
    }

    public static function record(
        string $title,
        string $eventType,
        string $severity,
        ?User $user = null,
        ?string $sourceIp = null,
        ?array $metadata = null,
        ?string $description = null,
        ?string $source = null,
        ?string $externalEventId = null,
    ): self {
        return static::query()->create([
            'title' => $title,
            'source' => $source,
            'external_event_id' => $externalEventId,
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
