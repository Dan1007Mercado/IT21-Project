<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityAlert extends Model
{
    use HasFactory;

    public const TYPE_BRUTE_FORCE = 'brute_force';

    public const TYPE_REPEATED_IP_ACTIVITY = 'repeated_ip_activity';

    protected $fillable = [
        'alert_id',
        'title',
        'alert_type',
        'severity',
        'description',
        'security_event_id',
        'source_ip',
        'metadata',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'assigned_to',
        'assigned_at',
        'incident_id',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'assigned_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function securityEvent(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class);
    }

    public function assignedAdministrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(SecurityAlertRemark::class)->latest();
    }

    public function typeLabel(): string
    {
        return match ($this->alert_type) {
            self::TYPE_BRUTE_FORCE => 'Brute-force login attempts',
            self::TYPE_REPEATED_IP_ACTIVITY => 'Repeated IP activity',
            default => $this->alert_type
                ? ucwords(str_replace(['_', '-'], ' ', $this->alert_type))
                : 'Unclassified',
        };
    }

    public static function generateAlertId(): string
    {
        $year = now()->format('Y');
        $prefix = "ALT-{$year}-";
        $latest = static::query()
            ->where('alert_id', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('alert_id');

        $sequence = 1;
        if ($latest !== null && preg_match('/^(?:ALT-\d{4}-)(\d{6})$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        do {
            $candidate = sprintf('ALT-%s-%06d', $year, $sequence);
            if (! static::query()->where('alert_id', $candidate)->exists()) {
                return $candidate;
            }

            $sequence++;
        } while (true);
    }
}
