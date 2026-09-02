<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $table = 'blocked_ips';

    protected $fillable = [
        'ip_address',
        'reason',
        'administrator_id',
        'blocked_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public static function isBlocked(string $ipAddress): bool
    {
        if ($ipAddress === '') {
            return false;
        }

        $record = static::query()
            ->where('ip_address', $ipAddress)
            ->where('status', 'active')
            ->orderByDesc('blocked_at')
            ->first();

        if (! $record) {
            return false;
        }

        return $record->isActive();
    }
}
