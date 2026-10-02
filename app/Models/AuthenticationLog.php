<?php

namespace App\Models;

use Database\Factories\AuthenticationLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticationLog extends Model
{
    /** @use HasFactory<AuthenticationLogFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'user_id',
        'attempted_identity',
        'ip_address',
        'country',
        'country_code',
        'region',
        'region_code',
        'city',
        'latitude',
        'longitude',
        'postal',
        'isp',
        'organization',
        'asn',
        'timezone',
        'user_agent',
        'device_type',
        'device_manufacturer',
        'device_model',
        'os_name',
        'os_version',
        'browser_name',
        'browser_version',
        'action',
        'status',
        'failure_reason',
        'route',
        'method',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }
}
