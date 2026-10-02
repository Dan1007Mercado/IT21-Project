<?php

namespace App\Services\Security;

use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;

final class DetectionEngine
{
    public function __construct(private EventCorrelationService $correlation) {}

    /**
     * @param  array{rule_key:string,rule_name:string,alert_type:string,title:string,description:string,severity:string,source?:string,source_ip?:?string,grouping_key:string,threshold?:int,window_seconds?:int,observed_count?:int,confidence?:float,risk_score?:int,affected_identity?:?string,route?:?string,request_activity_id?:?int,authentication_log_id?:?int,security_event_id?:?int,user_id?:?int,metadata?:array<string,mixed>}  $detection
     */
    public function record(array $detection): SecurityAlert
    {
        $source = $detection['source'] ?? config('intsec.source', 'intsec');
        if (isset($detection['security_event_id'])) {
            $source = SecurityEvent::query()->whereKey($detection['security_event_id'])->value('source') ?? $source;
        }
        $detection['source'] = $source;
        $deduplicationKey = hash('sha256', $source.'|'.$detection['rule_key'].'|'.$detection['grouping_key']);
        $cooldownStart = now()->subMinutes(IntsecSettings::getInt('alert_cooldown_minutes', 15));

        $existing = SecurityAlert::query()
            ->where('deduplication_key', $deduplicationKey)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->where('last_detected_at', '>=', $cooldownStart)
            ->latest('last_detected_at')
            ->first();

        if ($existing) {
            $existing->update([
                'occurrence_count' => $existing->occurrence_count + 1,
                'last_detected_at' => now(),
                'severity' => $this->higherSeverity($existing->severity, $detection['severity']),
                'metadata' => array_merge($existing->metadata ?? [], $this->evidence($detection), [
                    'deduplicated' => true,
                ]),
            ]);

            return $existing->fresh();
        }

        $alert = DB::transaction(function () use ($detection, $deduplicationKey): SecurityAlert {
            $event = isset($detection['security_event_id'])
                ? SecurityEvent::query()->findOrFail($detection['security_event_id'])
                : SecurityEvent::query()->create([
                    'title' => $detection['title'],
                    'source' => $detection['source'],
                    'event_type' => $detection['alert_type'],
                    'rule_key' => $detection['rule_key'],
                    'severity' => $detection['severity'],
                    'risk_score' => $detection['risk_score'] ?? $this->riskFor($detection['severity']),
                    'confidence' => $detection['confidence'] ?? 0.75,
                    'description' => $detection['description'],
                    'user_id' => $detection['user_id'] ?? null,
                    'request_activity_id' => $detection['request_activity_id'] ?? null,
                    'authentication_log_id' => $detection['authentication_log_id'] ?? null,
                    'source_ip' => $detection['source_ip'] ?? null,
                    'metadata' => $this->evidence($detection),
                    'status' => 'processed',
                    'occurred_at' => now(),
                ]);

            return SecurityAlert::query()->create([
                'alert_id' => SecurityAlert::generateAlertId(),
                'source' => $detection['source'],
                'title' => $detection['title'],
                'alert_type' => $detection['alert_type'],
                'rule_key' => $detection['rule_key'],
                'deduplication_key' => $deduplicationKey,
                'severity' => $detection['severity'],
                'description' => $detection['description'],
                'security_event_id' => $event->id,
                'source_ip' => $detection['source_ip'] ?? null,
                'metadata' => $this->evidence($detection),
                'status' => 'new',
                'occurrence_count' => 1,
                'occurred_at' => now(),
                'first_detected_at' => now(),
                'last_detected_at' => now(),
            ]);
        });

        $this->correlation->correlate($alert);

        return $alert;
    }

    private function evidence(array $detection): array
    {
        return array_filter(array_merge($detection['metadata'] ?? [], [
            'rule_key' => $detection['rule_key'],
            'rule_name' => $detection['rule_name'],
            'threshold' => $detection['threshold'] ?? null,
            'window_seconds' => $detection['window_seconds'] ?? null,
            'observed_count' => $detection['observed_count'] ?? null,
            'grouping_key' => $detection['grouping_key'],
            'source_ip' => $detection['source_ip'] ?? null,
            'affected_identity' => $detection['affected_identity'] ?? null,
            'route' => $detection['route'] ?? null,
            'confidence' => $detection['confidence'] ?? 0.75,
            'risk_score' => $detection['risk_score'] ?? $this->riskFor($detection['severity']),
        ]), fn (mixed $value): bool => $value !== null);
    }

    private function riskFor(string $severity): int
    {
        return match ($severity) {
            'Critical' => 95, 'High' => 80, 'Suspicious' => 60, 'Warning' => 35, default => 10,
        };
    }

    private function higherSeverity(string $left, string $right): string
    {
        $rank = ['Normal' => 0, 'Warning' => 1, 'Suspicious' => 2, 'High' => 3, 'Critical' => 4];

        return ($rank[$right] ?? 0) > ($rank[$left] ?? 0) ? $right : $left;
    }
}
