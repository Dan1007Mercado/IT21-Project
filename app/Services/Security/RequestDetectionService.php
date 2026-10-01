<?php

namespace App\Services\Security;

use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use Illuminate\Support\Str;

final class RequestDetectionService
{
    public function __construct(private DetectionEngine $engine) {}

    /** @return array<int, SecurityAlert> */
    public function evaluate(RequestActivity $activity): array
    {
        if (blank($activity->ip_address)) {
            return [];
        }

        $alerts = [];
        $windowSeconds = IntsecSettings::getInt('request_window_seconds', 60);
        $windowStart = $activity->occurred_at->copy()->subSeconds($windowSeconds);
        $ipQuery = RequestActivity::query()->where('ip_address', $activity->ip_address)
            ->whereBetween('occurred_at', [$windowStart, $activity->occurred_at]);
        $path = strtolower($activity->path);

        if (in_array($path, array_map('strtolower', config('intsec.decoy_paths', [])), true)) {
            $alerts[] = $this->record($activity, 'request.decoy_access', 'Decoy endpoint access', SecurityAlert::TYPE_DECOY_ACCESS, 'High', 1, 1, $windowSeconds, [
                'controlled_endpoint' => true,
            ]);
        }

        $suspiciousPaths = array_map('strtolower', config('intsec.suspicious_paths', []));
        if (in_array($path, $suspiciousPaths, true)) {
            $threshold = IntsecSettings::getInt('sensitive_path_probe_threshold', 2);
            $count = (clone $ipQuery)->whereIn('path', $suspiciousPaths)->count();
            if ($count >= $threshold) {
                $alerts[] = $this->record($activity, 'request.sensitive_path_probe', 'Repeated sensitive-path probing', SecurityAlert::TYPE_SENSITIVE_PATH_PROBE, 'Suspicious', $count, $threshold, $windowSeconds, [
                    'observed_paths' => (clone $ipQuery)->whereIn('path', $suspiciousPaths)->distinct()->limit(10)->pluck('path')->all(),
                ]);
            }
        }

        foreach ([401, 403, 404] as $status) {
            if ($activity->status_code !== $status) {
                continue;
            }
            $threshold = IntsecSettings::getInt('repeated_'.$status.'_threshold', $status === 404 ? 8 : 6);
            $count = (clone $ipQuery)->where('status_code', $status)->count();
            if ($count >= $threshold) {
                $alerts[] = $this->record($activity, 'request.repeated_'.$status, "Repeated HTTP {$status} responses", SecurityAlert::TYPE_REPEATED_STATUS, $status === 404 ? 'Suspicious' : 'High', $count, $threshold, $windowSeconds, ['status_code' => $status]);
            }
        }

        if (in_array($activity->status_code, [401, 403], true)
            && collect(config('intsec.protected_paths', []))->contains(fn (string $pattern): bool => Str::is($pattern, $activity->path))) {
            $threshold = IntsecSettings::getInt('repeated_403_threshold', 6);
            $count = (clone $ipQuery)->whereIn('status_code', [401, 403])
                ->where(function ($query): void {
                    foreach (config('intsec.protected_paths', []) as $pattern) {
                        $query->orWhere('path', 'like', str_replace('*', '%', $pattern));
                    }
                })->count();
            if ($count >= $threshold) {
                $alerts[] = $this->record($activity, 'request.protected_route_access', 'Repeated protected-route access', SecurityAlert::TYPE_PROTECTED_ROUTE_ACCESS, 'High', $count, $threshold, $windowSeconds);
            }
        }

        $ipThreshold = IntsecSettings::getInt('repeated_request_threshold', 60);
        $ipCount = (clone $ipQuery)->count();
        if ($ipCount >= $ipThreshold) {
            $alerts[] = $this->record($activity, 'request.repeated_ip', 'Repeated application requests from one IP', SecurityAlert::TYPE_REPEATED_IP_ACTIVITY, 'Suspicious', $ipCount, $ipThreshold, $windowSeconds);
        }

        $spikeThreshold = IntsecSettings::getInt('request_spike_threshold', 250);
        $globalCount = RequestActivity::query()->whereBetween('occurred_at', [$windowStart, $activity->occurred_at])->count();
        if ($globalCount >= $spikeThreshold) {
            $alerts[] = $this->record($activity, 'request.volume_spike', 'Application request volume spike', SecurityAlert::TYPE_REQUEST_SPIKE, 'Suspicious', $globalCount, $spikeThreshold, $windowSeconds, ['scope' => 'application']);
        }

        if ($alerts !== []) {
            $activity->update(['classification' => 'suspicious']);
        }

        return array_values(array_filter($alerts));
    }

    private function record(RequestActivity $activity, string $ruleKey, string $name, string $type, string $severity, int $count, int $threshold, int $windowSeconds, array $metadata = []): SecurityAlert
    {
        return $this->engine->record([
            'rule_key' => $ruleKey, 'rule_name' => $name, 'alert_type' => $type,
            'title' => $name, 'description' => "{$count} observations met threshold {$threshold} within {$windowSeconds} seconds.",
            'severity' => $severity, 'source_ip' => $activity->ip_address,
            'grouping_key' => $activity->ip_address.($type === SecurityAlert::TYPE_REPEATED_STATUS ? '|'.$activity->status_code : ''),
            'threshold' => $threshold, 'window_seconds' => $windowSeconds, 'observed_count' => $count,
            'route' => $activity->path, 'request_activity_id' => $activity->id, 'user_id' => $activity->user_id,
            'metadata' => $metadata,
        ]);
    }
}
