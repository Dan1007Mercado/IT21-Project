<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'action',
        'actor_id',
        'actor_type',
        'resource_type',
        'resource_id',
        'resource_name',
        'ip_address',
        'previous_value',
        'new_value',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(
        string $action,
        string $resourceType,
        ?string $resourceName = null,
        ?int $resourceId = null,
        mixed $previousValue = null,
        mixed $newValue = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?User $actor = null,
    ): self {
        return static::query()->create([
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $resourceName,
            'ip_address' => $ipAddress,
            'previous_value' => $previousValue === null ? null : $previousValue,
            'new_value' => $newValue === null ? null : $newValue,
            'description' => $description,
            'occurred_at' => now(),
        ]);
    }
}
