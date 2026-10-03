<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SecurityAlert;
use Illuminate\Support\Facades\DB;

final class EventCorrelationService
{
    public function correlate(SecurityAlert $alert): ?Incident
    {
        if ($alert->incident_id || blank($alert->source_ip)) {
            return null;
        }

        $windowStart = now()->subMinutes(IntsecSettings::getInt('correlation_window_minutes', 30));
        $related = SecurityAlert::query()
            ->forSource($alert->source)
            ->where('source_ip', $alert->source_ip)
            ->where('occurred_at', '>=', $windowStart)
            ->whereNotIn('status', ['dismissed'])
            ->get();

        $types = $related->pluck('alert_type')->unique();
        $failedThenSuccess = $alert->alert_type === SecurityAlert::TYPE_FAILED_THEN_SUCCESS;
        $sqlInjection = $alert->alert_type === SecurityAlert::TYPE_SQL_INJECTION;
        $probeAndAuth = $types->contains(SecurityAlert::TYPE_SENSITIVE_PATH_PROBE)
            && $types->intersect([
                SecurityAlert::TYPE_AUTH_BRUTE_FORCE,
                SecurityAlert::TYPE_PASSWORD_SPRAY,
                SecurityAlert::TYPE_DISTRIBUTED_ACCOUNT_ATTACK,
            ])->isNotEmpty();

        if (! $failedThenSuccess && ! $probeAndAuth && ! $sqlInjection) {
            return null;
        }

        if ($sqlInjection) {
            $related = $related->where('alert_type', SecurityAlert::TYPE_SQL_INJECTION);
        }

        $incidentType = $sqlInjection ? 'sql_injection_activity' : 'correlated_activity';

        $existing = Incident::query()
            ->forSource($alert->source)
            ->where('source_ip', $alert->source_ip)
            ->where('incident_type', $incidentType)
            ->where('last_detected_at', '>=', $windowStart)
            ->whereNotIn('status', ['resolved', 'closed', 'false_positive'])
            ->latest('last_detected_at')
            ->first();

        if ($existing) {
            $existing->update(['event_count' => $related->sum('occurrence_count'), 'last_detected_at' => now()]);
            $related->whereNull('incident_id')->each->update(['incident_id' => $existing->id]);

            return $existing;
        }

        return DB::transaction(function () use ($alert, $related, $failedThenSuccess, $sqlInjection, $incidentType): Incident {
            $incident = Incident::query()->create([
                'title' => $sqlInjection ? 'SQL injection activity detected' : ($failedThenSuccess ? 'Failed authentication followed by success' : 'Correlated probing and authentication activity'),
                'source' => $alert->source,
                'description' => 'Multiple explainable detections were correlated within the configured investigation window.',
                'incident_type' => $incidentType,
                'severity' => $failedThenSuccess ? 'High' : 'High',
                'status' => 'open',
                'source_ip' => $alert->source_ip,
                'security_event_id' => $alert->security_event_id,
                'detection_reason' => $sqlInjection ? 'A high-confidence contextual SQL injection rule matched' : ($failedThenSuccess ? 'Failed attempts followed by successful authentication' : 'Sensitive-path probing followed by authentication attacks'),
                'detection_rule' => $sqlInjection ? $alert->rule_key : ($failedThenSuccess ? 'auth.failed_then_success' : 'correlation.probe_and_authentication'),
                'event_count' => $related->sum('occurrence_count'),
                'first_detected_at' => $related->min('occurred_at') ?? now(),
                'last_detected_at' => now(),
            ]);

            SecurityAlert::query()->whereIn('id', $related->pluck('id'))->update(['incident_id' => $incident->id]);
            $incident->remarks()->create(['remark' => 'Incident created automatically from correlated alert evidence.']);
            AuditLog::record('automatic_incident_created', 'incident', $incident->incident_id, $incident->id, null, [
                'alert_ids' => $related->pluck('alert_id')->all(), 'source_ip' => $alert->source_ip,
            ], 'Explainable correlation created an investigation case.', $alert->source_ip);

            return $incident;
        });
    }
}
