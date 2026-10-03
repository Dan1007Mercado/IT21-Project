<?php

namespace App\Services\Security;

use App\Models\RequestActivity;
use App\Models\SecurityEvent;

final class ExternalRequestActivityService
{
    public function __construct(
        private IpClassifier $ipClassifier,
        private UserAgentClassifier $userAgentClassifier,
        private IpEnrichmentService $ipEnrichment,
        private RequestDetectionService $requestDetection,
        private TelemetryMetadataSanitizer $metadataSanitizer,
    ) {}

    /** @return array{activity: RequestActivity, duplicate: bool} */
    public function ingest(array $payload): array
    {
        $agent = $this->userAgentClassifier->analyze($payload['user_agent'] ?? null);
        $activity = RequestActivity::query()->firstOrCreate([
            'request_id' => $payload['request_id'],
            'source' => $payload['source'],
        ], [
            'user_id' => null,
            'ip_address' => $payload['ip'],
            'ip_type' => $this->ipClassifier->classify($payload['ip']),
            'method' => strtoupper($payload['method']),
            'path' => $payload['path'],
            'route_name' => $payload['route_name'] ?? null,
            'status_code' => $payload['status_code'],
            'user_agent' => $agent['user_agent'],
            'referer' => null,
            'is_authenticated' => $payload['is_authenticated'],
            'duration_ms' => $payload['duration_ms'] ?? null,
            'request_size' => $payload['request_size'] ?? null,
            'response_size' => $payload['response_size'] ?? null,
            'classification' => 'normal',
            'metadata' => array_merge($this->metadataSanitizer->sanitize($payload['metadata'] ?? []), [
                'device_type' => $agent['device_type'],
                'browser_name' => $agent['browser_name'],
                'os_name' => $agent['os_name'],
            ]),
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ]);

        if (! $activity->wasRecentlyCreated) {
            return ['activity' => $activity, 'duplicate' => true];
        }

        SecurityEvent::query()
            ->where('source', $activity->source)
            ->whereNull('request_activity_id')
            ->where('metadata->request_id', $activity->request_id)
            ->update(['request_activity_id' => $activity->id]);

        $this->ipEnrichment->observe($activity->ip_address);
        $this->requestDetection->evaluate($activity);

        return ['activity' => $activity->fresh(), 'duplicate' => false];
    }
}
