<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;

class ExternalSecurityEventService
{
    public function __construct(protected IpActivityIncidentService $ipActivityIncidents)
    {
    }

    /** @return array{event: SecurityEvent, alert: ?SecurityAlert, incident: ?Incident, duplicate: bool} */
    public function ingest(array $payload): array
    {
        if (! empty($payload['event_id']) && ($existing = SecurityEvent::query()->where('external_event_id', $payload['event_id'])->first())) {
            return ['event' => $existing, 'alert' => null, 'incident' => null, 'duplicate' => true];
        }

        return DB::transaction(function () use ($payload): array {
            $source = $payload['source'];
            $ip = $payload['ip'];
            $eventType = $payload['event_type'];
            $windowStart = now()->subMinutes(IntsecSettings::getInt('login_attempt_window_minutes', 5));
            $failedCount = SecurityEvent::query()->where('source', $source)->where('source_ip', $ip)
                ->where('event_type', 'login_failed')->where('occurred_at', '>=', $windowStart)->count() + ($eventType === 'login_failed' ? 1 : 0);
            $activityCount = SecurityEvent::query()->where('source', $source)->where('source_ip', $ip)
                ->where('occurred_at', '>=', $windowStart)->count() + 1;
            $contextuallySuspicious = $this->isSuspiciousLoginContext($source, $ip, $eventType, $payload['user_agent'] ?? null);

            $severity = $this->classify($eventType, $failedCount, $activityCount, $contextuallySuspicious);
            $event = SecurityEvent::query()->create([
                'title' => $this->titleFor($eventType),
                'source' => $source,
                'external_event_id' => $payload['event_id'] ?? null,
                'event_type' => $eventType,
                'severity' => $severity,
                'description' => $payload['message'],
                'source_ip' => $ip,
                'metadata' => array_merge($payload['metadata'] ?? [], [
                    'route' => $payload['route'], 'method' => strtoupper($payload['method']),
                    'user_agent' => $payload['user_agent'] ?? null,
                    'reported_at' => $payload['occurred_at'] ?? now()->toIso8601String(),
                    'reported_severity' => $payload['severity'] ?? null,
                    'suspicious_login_context' => $contextuallySuspicious,
                ]),
                'status' => 'processed',
                'occurred_at' => $payload['occurred_at'] ?? now(),
            ]);

            $alert = null;
            if ($eventType === 'login_failed' && $failedCount >= IntsecSettings::getInt('repeated_authentication_threshold', 5)) {
                $alert = $this->upsertBruteForceAlert($event, $failedCount);
            }

            $incident = $this->ipActivityIncidents->evaluate(
                $ip, $activityCount, IntsecSettings::getInt('repeated_ip_activity_threshold', 10), null, null, $event,
            );

            AuditLog::record('external_security_event_received', 'security_event', $event->title, $event->id, null,
                ['source' => $source, 'event_type' => $eventType, 'severity' => $severity],
                'Authenticated external security event received and classified.', $ip);

            return ['event' => $event, 'alert' => $alert, 'incident' => $incident, 'duplicate' => false];
        });
    }

    private function classify(string $eventType, int $failedCount, int $activityCount, bool $contextuallySuspicious): string
    {
        if ($eventType === 'monitored_login_attempt' || $eventType === 'unauthorized_access') return 'Suspicious';
        if ($contextuallySuspicious) return 'Suspicious';
        if ($eventType === 'login_failed' && $failedCount >= IntsecSettings::getInt('repeated_authentication_threshold', 5)) return 'High';
        if ($activityCount > IntsecSettings::getInt('repeated_ip_activity_threshold', 10) * 3) return 'Critical';
        if ($activityCount > IntsecSettings::getInt('repeated_ip_activity_threshold', 10)) return 'Suspicious';
        return $eventType === 'login_failed' ? 'Warning' : 'Normal';
    }

    private function isSuspiciousLoginContext(string $source, string $ip, string $eventType, ?string $userAgent): bool
    {
        if ($eventType !== 'login_success' || blank($userAgent)) return false;

        // The monitored application's source IP and user-agent are evaluated
        // against recent successful activity. This is intentionally contextual
        // rather than a client-supplied severity claim.
        return SecurityEvent::query()->where('source', $source)->where('source_ip', $ip)
            ->where('event_type', 'login_success')->where('occurred_at', '>=', now()->subDay())
            ->get(['metadata'])->contains(function (SecurityEvent $event) use ($userAgent): bool {
                return filled($event->metadata['user_agent'] ?? null) && $event->metadata['user_agent'] !== $userAgent;
            });
    }

    private function titleFor(string $eventType): string
    {
        return ucwords(str_replace('_', ' ', $eventType)).' reported by monitored application';
    }

    private function upsertBruteForceAlert(SecurityEvent $event, int $count): SecurityAlert
    {
        $alert = SecurityAlert::query()->where('alert_type', SecurityAlert::TYPE_BRUTE_FORCE)
            ->where('source_ip', $event->source_ip)->where('status', 'new')
            ->where('occurred_at', '>=', now()->subMinutes(IntsecSettings::getInt('login_attempt_window_minutes', 5)))->latest('occurred_at')->first();
        if ($alert) {
            $alert->update(['security_event_id' => $event->id, 'severity' => 'High', 'metadata' => ['failed_attempt_count' => $count]]);
            return $alert;
        }
        return SecurityAlert::query()->create([
            'alert_id' => SecurityAlert::generateAlertId(), 'title' => 'Repeated failed authentication from monitored application',
            'alert_type' => SecurityAlert::TYPE_BRUTE_FORCE, 'severity' => 'High',
            'description' => "{$count} failed authentication events exceeded the configured threshold.",
            'security_event_id' => $event->id, 'source_ip' => $event->source_ip,
            'metadata' => ['failed_attempt_count' => $count, 'source' => $event->source], 'status' => 'new', 'occurred_at' => now(),
        ]);
    }
}
