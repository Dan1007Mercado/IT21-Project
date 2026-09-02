<?php

namespace App\Models;

use Database\Factories\AuthenticationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticationLog extends Model
{
    /** @use HasFactory<AuthenticationLogFactory> */
    use HasFactory;

    protected $fillable = [
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
}
