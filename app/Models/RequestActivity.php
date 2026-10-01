<?php

namespace App\Models;

use Database\Factories\RequestActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestActivity extends Model
{
    /** @use HasFactory<RequestActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id', 'source', 'user_id', 'ip_address', 'ip_type', 'method', 'path',
        'route_name', 'status_code', 'user_agent', 'referer', 'is_authenticated',
        'duration_ms', 'request_size', 'response_size', 'classification', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_authenticated' => 'boolean',
            'duration_ms' => 'integer',
            'request_size' => 'integer',
            'response_size' => 'integer',
            'status_code' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSince(Builder $query, \DateTimeInterface $time): Builder
    {
        return $query->where('occurred_at', '>=', $time);
    }
}
