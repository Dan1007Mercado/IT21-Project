<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IpActivityIncidentService
{
    public function evaluate(string $ipAddress, int $activityCount, ?int $threshold = null, ?User $actor = null, ?string $actorIp = null, ?SecurityEvent $securityEvent = null): ?Incident
    {
        $threshold ??= IntsecSettings::getInt('repeated_ip_activity_threshold', 10);

        if ($ipAddress === '' || $activityCount <= $threshold) {
            return null;
        }

        $source = $securityEvent?->source ?? config('intsec.source', 'intsec');
        $existing = $this->findActiveIncident($ipAddress, $source);

        if ($existing) {
            $previousCount = $existing->event_count ?? 0;
            $existing->event_count = max($previousCount, $activityCount);
            $existing->last_detected_at = now();
            $existing->save();

            SecurityAlert::query()
                ->where('incident_id', $existing->id)
                ->latest('occurred_at')
                ->first()
                ?->update([
                    'metadata' => [
                        'count' => $existing->event_count,
                        'threshold' => $threshold,
                        'updated_in_existing_window' => true,
                    ],
                ]);

            AuditLog::record(
                'incident_updated_by_monitor',
                'incident',
                $existing->incident_id,
                $existing->id,
                ['event_count' => $previousCount],
                ['event_count' => $existing->event_count, 'threshold' => $threshold],
                'Monitoring updated an existing IP activity incident in the current event window.',
                $actorIp,
                $actor,
            );

            return $existing;
        }

        return DB::transaction(function () use ($ipAddress, $activityCount, $threshold, $actor, $actorIp, $securityEvent, $source): Incident {
            $title = "Request spike detected from {$ipAddress}";
            $severity = $this->severityFor($activityCount, $threshold);

            $event = $securityEvent ?? SecurityEvent::record(
                $title,
                'ip_activity_spike',
                $severity,
                null,
                $ipAddress,
                ['count' => $activityCount, 'threshold' => $threshold],
                "Detected {$activityCount} requests from {$ipAddress}; configured threshold is {$threshold}.",
                $source,
            );

            $alert = SecurityAlert::query()->create([
                'alert_id' => SecurityAlert::generateAlertId(),
                'source' => $source,
                'title' => $title,
                'alert_type' => SecurityAlert::TYPE_REPEATED_IP_ACTIVITY,
                'severity' => $severity,
                'description' => "Activity exceeded the configured threshold: {$activityCount} requests from {$ipAddress} against threshold {$threshold}.",
                'security_event_id' => $event->id,
                'source_ip' => $ipAddress,
                'metadata' => [
                    'detection_rule' => 'repeated_ip_activity_threshold',
                    'count' => $activityCount,
                    'threshold' => $threshold,
                ],
                'status' => 'new',
                'occurred_at' => now(),
            ]);

            $incident = Incident::query()->create([
                'source' => $source,
                'title' => $title,
                'description' => "Activity exceeded the configured threshold. Source {$ipAddress} generated {$activityCount} requests; threshold is {$threshold}.",
                'incident_type' => 'ip_activity',
                'severity' => $severity,
                'status' => 'open',
                'source_ip' => $ipAddress,
                'security_event_id' => $event->id,
                'detection_reason' => 'Request/IP activity threshold exceeded',
                'detection_rule' => 'repeated_ip_activity_threshold',
                'event_count' => $activityCount,
                'first_detected_at' => now(),
                'last_detected_at' => now(),
            ]);

            $alert->update(['incident_id' => $incident->id]);

            $incident->remarks()->create([
                'author_id' => null,
                'remark' => "Automatic incident created: {$activityCount} requests exceeded configured threshold {$threshold}.",
            ]);

            AuditLog::record(
                'automatic_incident_created',
                'incident',
                $incident->incident_id,
                $incident->id,
                null,
                [
                    'alert_id' => $alert->alert_id,
                    'security_event_id' => $event->id,
                    'event_count' => $activityCount,
                    'threshold' => $threshold,
                    'source_ip' => $ipAddress,
                ],
                'An automatic incident was created by monitoring logic.',
                $actorIp,
                $actor,
            );

            return $incident;
        });
    }

    protected function findActiveIncident(string $ipAddress, string $source): ?Incident
    {
        return Incident::query()
            ->forSource($source)
            ->where('source_ip', $ipAddress)
            ->where('incident_type', 'ip_activity')
            ->where('detection_rule', 'repeated_ip_activity_threshold')
            ->where('last_detected_at', '>=', now()->subMinutes(60))
            ->whereNotIn('status', ['resolved', 'closed', 'false_positive'])
            ->orderByDesc('last_detected_at')
            ->first();
    }

    protected function severityFor(int $activityCount, int $threshold): string
    {
        if ($activityCount > $threshold * 3) {
            return 'Critical';
        }

        if ($activityCount > $threshold * 2) {
            return 'High';
        }

        return 'Suspicious';
    }
}
