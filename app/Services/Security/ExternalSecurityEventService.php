<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\AuthenticationLog;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;

class ExternalSecurityEventService
{
    public function __construct(
        protected IpActivityIncidentService $ipActivityIncidents,
        protected DetectionEngine $detectionEngine,
        protected UserAgentClassifier $userAgentClassifier,
        protected IpEnrichmentService $ipEnrichment,
        protected TelemetryMetadataSanitizer $metadataSanitizer,
    ) {}

    /** @return array{event: SecurityEvent, alert: ?SecurityAlert, incident: ?Incident, duplicate: bool} */
    public function ingest(array $payload): array
    {
        if (! empty($payload['event_id']) && ($existing = SecurityEvent::query()
            ->where('source', $payload['source'])
            ->where('external_event_id', $payload['event_id'])
            ->first())) {
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
            $metadata = $this->metadataSanitizer->sanitize($payload['metadata'] ?? []);

            $authenticationLog = $this->authenticationLog($payload, $metadata);

            $severity = $this->classify($eventType, $failedCount, $activityCount, $contextuallySuspicious);
            $event = SecurityEvent::query()->create([
                'title' => $this->titleFor($eventType),
                'source' => $source,
                'external_event_id' => $payload['event_id'] ?? null,
                'event_type' => $eventType,
                'severity' => $severity,
                'description' => $payload['message'],
                'authentication_log_id' => $authenticationLog?->id,
                'source_ip' => $ip,
                'metadata' => array_merge($metadata, [
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
            if ($eventType === 'sql_injection_attempt') {
                $rule = $this->sqlInjectionRule($metadata['rule'] ?? null);
                $confidence = $this->sqlInjectionConfidence($rule, $metadata['confidence'] ?? null);
                $route = (string) $payload['route'];
                $parameter = filled($metadata['parameter'] ?? null) ? mb_substr((string) $metadata['parameter'], 0, 120) : 'unknown';
                $event->update([
                    'rule_key' => 'external.sqli.'.strtolower($rule),
                    'severity' => 'High',
                    'risk_score' => 85,
                    'confidence' => $confidence,
                    'metadata' => array_merge($event->metadata ?? [], [
                        'rule' => $rule,
                        'parameter' => $parameter,
                        'confidence' => $confidence,
                    ]),
                ]);
                $alert = $this->detectionEngine->record([
                    'rule_key' => 'external.sqli.'.strtolower($rule),
                    'rule_name' => 'Contextual SQL injection detection: '.$rule,
                    'alert_type' => SecurityAlert::TYPE_SQL_INJECTION,
                    'title' => 'SQL injection attempt reported by monitored application',
                    'description' => 'A monitored request matched an authoritative contextual SQL injection rule.',
                    'severity' => 'High',
                    'source_ip' => $ip,
                    'grouping_key' => $source.'|'.$ip.'|'.$route.'|'.$parameter.'|'.$rule,
                    'threshold' => 1,
                    'window_seconds' => IntsecSettings::getInt('alert_cooldown_minutes', 15) * 60,
                    'observed_count' => 1,
                    'confidence' => $confidence,
                    'risk_score' => 85,
                    'route' => $route,
                    'security_event_id' => $event->id,
                    'source' => $source,
                    'metadata' => ['source' => $source, 'parameter' => $parameter, 'request_id' => $metadata['request_id'] ?? null],
                ]);
            } elseif ($eventType === 'login_failed' && $failedCount >= IntsecSettings::getInt('repeated_authentication_threshold', 5)) {
                $alert = $this->detectionEngine->record([
                    'rule_key' => 'external.auth.repeated_ip_failures',
                    'rule_name' => 'Repeated external authentication failures',
                    'alert_type' => SecurityAlert::TYPE_BRUTE_FORCE,
                    'title' => 'Repeated failed authentication from monitored application',
                    'description' => "{$failedCount} failed authentication events exceeded the configured threshold.",
                    'severity' => 'High',
                    'source_ip' => $ip,
                    'grouping_key' => $source.'|'.$ip,
                    'threshold' => IntsecSettings::getInt('repeated_authentication_threshold', 5),
                    'window_seconds' => IntsecSettings::getInt('login_attempt_window_minutes', 5) * 60,
                    'observed_count' => $failedCount,
                    'security_event_id' => $event->id,
                    'source' => $source,
                    'metadata' => ['source' => $source],
                ]);
            } elseif ($eventType === 'monitored_login_attempt') {
                $alert = $this->detectionEngine->record([
                    'rule_key' => 'external.decoy_access',
                    'rule_name' => 'Monitored decoy login access',
                    'alert_type' => SecurityAlert::TYPE_DECOY_ACCESS,
                    'title' => 'Monitored decoy login endpoint accessed',
                    'description' => 'A monitored application reported access to its controlled decoy login endpoint.',
                    'severity' => 'High',
                    'source_ip' => $ip,
                    'grouping_key' => $source.'|'.$ip,
                    'threshold' => 1,
                    'window_seconds' => 1,
                    'observed_count' => 1,
                    'security_event_id' => $event->id,
                    'source' => $source,
                    'metadata' => ['source' => $source, 'controlled_endpoint' => true],
                ]);
            }

            $incident = $this->ipActivityIncidents->evaluate(
                $ip, $activityCount, IntsecSettings::getInt('repeated_ip_activity_threshold', 10), null, null, $event,
            );

            AuditLog::record('external_security_event_received', 'security_event', $event->title, $event->id, null,
                ['source' => $source, 'event_type' => $eventType, 'severity' => $severity],
                'Authenticated external security event received and classified.', $ip);

            $this->ipEnrichment->observe($ip);

            return ['event' => $event, 'alert' => $alert, 'incident' => $incident, 'duplicate' => false];
        });
    }

    /** @param array<string, mixed> $metadata */
    private function authenticationLog(array $payload, array $metadata): ?AuthenticationLog
    {
        $mapping = [
            'login_failed' => ['login', 'failed'],
            'login_success' => ['login', 'successful'],
            'logout' => ['logout', 'successful'],
        ];

        if (! isset($mapping[$payload['event_type']])) {
            return null;
        }

        [$action, $status] = $mapping[$payload['event_type']];
        $agent = $this->userAgentClassifier->analyze($payload['user_agent'] ?? null);

        return AuthenticationLog::query()->create([
            'source' => $payload['source'],
            'user_id' => null,
            'attempted_identity' => filled($metadata['identity'] ?? null) ? mb_strtolower(trim((string) $metadata['identity'])) : null,
            'ip_address' => $payload['ip'],
            'user_agent' => $agent['user_agent'],
            'device_type' => $agent['device_type'],
            'device_manufacturer' => $agent['device_manufacturer'],
            'device_model' => $agent['device_model'],
            'os_name' => $agent['os_name'],
            'os_version' => $agent['os_version'],
            'browser_name' => $agent['browser_name'],
            'browser_version' => $agent['browser_version'],
            'action' => $action,
            'status' => $status,
            'failure_reason' => $status === 'failed' ? 'credentials_rejected_by_source' : null,
            'route' => $payload['route'],
            'method' => strtoupper($payload['method']),
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ]);
    }

    private function classify(string $eventType, int $failedCount, int $activityCount, bool $contextuallySuspicious): string
    {
        if ($eventType === 'sql_injection_attempt') {
            return 'High';
        }
        if ($eventType === 'monitored_login_attempt' || $eventType === 'unauthorized_access') {
            return 'Suspicious';
        }
        if ($contextuallySuspicious) {
            return 'Suspicious';
        }
        if ($eventType === 'login_failed' && $failedCount >= IntsecSettings::getInt('repeated_authentication_threshold', 5)) {
            return 'High';
        }
        if ($activityCount > IntsecSettings::getInt('repeated_ip_activity_threshold', 10) * 3) {
            return 'Critical';
        }
        if ($activityCount > IntsecSettings::getInt('repeated_ip_activity_threshold', 10)) {
            return 'Suspicious';
        }

        return $eventType === 'login_failed' ? 'Warning' : 'Normal';
    }

    private function isSuspiciousLoginContext(string $source, string $ip, string $eventType, ?string $userAgent): bool
    {
        if ($eventType !== 'login_success' || blank($userAgent)) {
            return false;
        }

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

    private function sqlInjectionRule(mixed $rule): string
    {
        $allowed = ['SQLI_TIME_BASED', 'SQLI_UNION_SELECT', 'SQLI_SCHEMA_PROBE', 'SQLI_STACKED_QUERY', 'SQLI_TAUTOLOGY', 'SQLI_COMMENT_OPERATOR'];

        return in_array($rule, $allowed, true) ? $rule : 'SQLI_CONTEXTUAL_MATCH';
    }

    private function sqlInjectionConfidence(string $rule, mixed $reported): float
    {
        $minimum = $rule === 'SQLI_CONTEXTUAL_MATCH' ? 0.75 : 0.90;
        $value = is_numeric($reported) ? (float) $reported : $minimum;

        return max($minimum, min(0.99, $value));
    }
}
