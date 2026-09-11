<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $table = 'blocked_ips';

    public const ACTION_ALLOW = 'allow';

    public const ACTION_BLOCK = 'block';

    public const SOURCES = ['manual', 'automatic', 'alert', 'incident', 'system'];

    protected $fillable = [
        'ip_address',
        'action',
        'is_enabled',
        'source',
        'name',
        'reason',
        'description',
        'administrator_id',
        'alert_id',
        'incident_id',
        'blocked_at',
        'expires_at',
        'status',
        'match_count',
        'last_matched_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_matched_at' => 'datetime',
        'is_enabled' => 'boolean',
        'match_count' => 'integer',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SecurityAlert::class, 'alert_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /**
     * Enforcing rules only: active status + enabled + not expired.
     * Action (allow/block) is evaluated by IpManagementService.
     */
    public function scopeEnforcing(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('is_enabled', true)
            ->where(function (Builder $nested): void {
                $nested->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (! $this->is_enabled) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && ! $this->expires_at->isFuture();
    }

    public function isAllowRule(): bool
    {
        return $this->action === self::ACTION_ALLOW;
    }

    public function isBlockRule(): bool
    {
        return $this->action !== self::ACTION_ALLOW;
    }

    /**
     * Legacy exact-IP block check. Kept for backward compatibility;
     * new code should use IpManagementService which is CIDR-aware and
     * understands allow/block precedence.
     */
    public static function isBlocked(string $ipAddress): bool
    {
        return app(\App\Services\Security\IpManagementService::class)->isBlocked($ipAddress);
    }
}
