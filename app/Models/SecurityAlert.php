<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityAlert extends Model
{
    use HasFactory;

    public const TYPE_BRUTE_FORCE = 'brute_force';

    public const TYPE_REPEATED_IP_ACTIVITY = 'repeated_ip_activity';

    public const TYPE_AUTH_BRUTE_FORCE = 'authentication_brute_force';

    public const TYPE_REPEATED_AUTH_FAILURES = 'repeated_authentication_failures';

    public const TYPE_PASSWORD_SPRAY = 'password_spray';

    public const TYPE_DISTRIBUTED_ACCOUNT_ATTACK = 'distributed_account_attack';

    public const TYPE_FAILED_THEN_SUCCESS = 'failed_then_success';

    public const TYPE_SUSPICIOUS_LOGIN = 'suspicious_login';

    public const TYPE_REQUEST_SPIKE = 'request_spike';

    public const TYPE_REPEATED_STATUS = 'repeated_http_status';

    public const TYPE_SENSITIVE_PATH_PROBE = 'sensitive_path_probe';

    public const TYPE_DECOY_ACCESS = 'decoy_access';

    public const TYPE_PROTECTED_ROUTE_ACCESS = 'protected_route_access';

    protected $fillable = [
        'alert_id',
        'source',
        'title',
        'alert_type',
        'rule_key',
        'deduplication_key',
        'severity',
        'description',
        'security_event_id',
        'source_ip',
        'metadata',
        'status',
        'occurrence_count',
        'acknowledged_by',
        'acknowledged_at',
        'assigned_to',
        'assigned_at',
        'incident_id',
        'occurred_at',
        'first_detected_at',
        'last_detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'assigned_at' => 'datetime',
        'occurred_at' => 'datetime',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'occurrence_count' => 'integer',
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

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function typeLabel(): string
    {
        return match ($this->alert_type) {
            self::TYPE_BRUTE_FORCE => 'Brute-force login attempts',
            self::TYPE_AUTH_BRUTE_FORCE => 'Account brute-force attempts',
            self::TYPE_REPEATED_AUTH_FAILURES => 'Repeated authentication failures',
            self::TYPE_PASSWORD_SPRAY => 'Password spraying',
            self::TYPE_DISTRIBUTED_ACCOUNT_ATTACK => 'Distributed account attack',
            self::TYPE_FAILED_THEN_SUCCESS => 'Failures followed by success',
            self::TYPE_SUSPICIOUS_LOGIN => 'Suspicious successful login',
            self::TYPE_REQUEST_SPIKE => 'Application request spike',
            self::TYPE_REPEATED_STATUS => 'Repeated HTTP errors',
            self::TYPE_SENSITIVE_PATH_PROBE => 'Sensitive-path probing',
            self::TYPE_DECOY_ACCESS => 'Decoy endpoint access',
            self::TYPE_PROTECTED_ROUTE_ACCESS => 'Repeated protected-route access',
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
