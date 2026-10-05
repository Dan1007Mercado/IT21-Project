<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;

final class MonitoringOverviewQueryService
{
    public function __construct(private RequestActivityQueryService $requestQueries) {}

    /** @return array<string, mixed> */
    public function forSource(string $source): array
    {
        $currentStart = now()->subDay();
        $previousStart = now()->subDays(2);

        $requests = RequestActivity::query()
            ->forSource($source)
            ->where('occurred_at', '>=', $previousStart)
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS current_count,
                SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN 1 ELSE 0 END) AS previous_count,
                COUNT(DISTINCT CASE WHEN occurred_at >= ? THEN ip_address END) AS current_unique_ips,
                COUNT(DISTINCT CASE WHEN occurred_at >= ? AND occurred_at < ? THEN ip_address END) AS previous_unique_ips',
                [$currentStart, $previousStart, $currentStart, $currentStart, $previousStart, $currentStart],
            )
            ->first();

        $authentication = AuthenticationLog::query()
            ->forSource($source)
            ->where('action', 'login')
            ->where('occurred_at', '>=', $previousStart)
            ->selectRaw(
                "SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS current_count,
                SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN 1 ELSE 0 END) AS previous_count,
                SUM(CASE WHEN occurred_at >= ? AND status = 'successful' THEN 1 ELSE 0 END) AS successful_count,
                SUM(CASE WHEN occurred_at >= ? AND status = 'failed' THEN 1 ELSE 0 END) AS failed_count",
                [$currentStart, $previousStart, $currentStart, $currentStart, $currentStart],
            )
            ->first();

        $events = SecurityEvent::query()
            ->forSource($source)
            ->where('occurred_at', '>=', $previousStart)
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS current_count,
                SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN 1 ELSE 0 END) AS previous_count',
                [$currentStart, $previousStart, $currentStart],
            )
            ->first();

        $alerts = SecurityAlert::query()
            ->forSource($source)
            ->whereIn('status', ['new', 'acknowledged', 'investigating'])
            ->selectRaw("COUNT(*) AS open_count, SUM(CASE WHEN severity IN ('High', 'Critical') THEN 1 ELSE 0 END) AS high_critical_count")
            ->first();

        $incidents = Incident::query()
            ->forSource($source)
            ->whereIn('status', ['open', 'investigating', 'contained'])
            ->selectRaw("COUNT(*) AS open_count, SUM(CASE WHEN severity IN ('High', 'Critical') THEN 1 ELSE 0 END) AS high_critical_count")
            ->first();

        $requestCount = (int) ($requests?->current_count ?? 0);
        $previousRequestCount = (int) ($requests?->previous_count ?? 0);
        $uniqueIpCount = (int) ($requests?->current_unique_ips ?? 0);
        $previousUniqueIpCount = (int) ($requests?->previous_unique_ips ?? 0);
        $authenticationCount = (int) ($authentication?->current_count ?? 0);
        $previousAuthenticationCount = (int) ($authentication?->previous_count ?? 0);
        $successfulAuthenticationCount = (int) ($authentication?->successful_count ?? 0);
        $failedAuthenticationCount = (int) ($authentication?->failed_count ?? 0);
        $securityEventCount = (int) ($events?->current_count ?? 0);
        $previousSecurityEventCount = (int) ($events?->previous_count ?? 0);
        $openAlertCount = (int) ($alerts?->open_count ?? 0);
        $highCriticalAlertCount = (int) ($alerts?->high_critical_count ?? 0);
        $openIncidentCount = (int) ($incidents?->open_count ?? 0);
        $highCriticalIncidentCount = (int) ($incidents?->high_critical_count ?? 0);

        return [
            'periodLabel' => 'Last 24 hours',
            'kpis' => [
                [
                    'label' => 'Requests',
                    'value' => $requestCount,
                    'tone' => 'cyan',
                    'context' => 'Application requests observed',
                    'trend' => $this->comparison($requestCount, $previousRequestCount),
                ],
                [
                    'label' => 'Unique IPs',
                    'value' => $uniqueIpCount,
                    'tone' => 'emerald',
                    'context' => 'Distinct non-null source addresses',
                    'trend' => $this->comparison($uniqueIpCount, $previousUniqueIpCount),
                ],
                [
                    'label' => 'Authentication attempts',
                    'value' => $authenticationCount,
                    'tone' => $failedAuthenticationCount > 0 ? 'amber' : 'cyan',
                    'context' => $authenticationCount > 0
                        ? $successfulAuthenticationCount.' successful · '.$failedAuthenticationCount.' failed'
                        : 'No login attempts in this period',
                    'trend' => $this->comparison($authenticationCount, $previousAuthenticationCount),
                    'rate' => $authenticationCount > 0
                        ? round(($failedAuthenticationCount / $authenticationCount) * 100, 1)
                        : null,
                ],
                [
                    'label' => 'Security events',
                    'value' => $securityEventCount,
                    'tone' => $securityEventCount > 0 ? 'amber' : 'cyan',
                    'context' => 'Interpreted security activity',
                    'trend' => $this->comparison($securityEventCount, $previousSecurityEventCount),
                ],
                [
                    'label' => 'Open alerts',
                    'value' => $openAlertCount,
                    'tone' => $highCriticalAlertCount > 0 ? 'red' : 'amber',
                    'context' => $highCriticalAlertCount.' high or critical',
                    'trend' => null,
                ],
                [
                    'label' => 'Open incidents',
                    'value' => $openIncidentCount,
                    'tone' => $highCriticalIncidentCount > 0 ? 'red' : 'amber',
                    'context' => $highCriticalIncidentCount.' high or critical',
                    'trend' => null,
                ],
            ],
            'requestTrend' => $this->requestQueries->hourlyTrend(24, $source),
            'authenticationBreakdown' => [
                'successful' => $successfulAuthenticationCount,
                'failed' => $failedAuthenticationCount,
            ],
            'eventSeverityDistribution' => SecurityEvent::query()
                ->forSource($source)
                ->where('occurred_at', '>=', $currentStart)
                ->selectRaw('severity, COUNT(*) AS total')
                ->groupBy('severity')
                ->pluck('total', 'severity')
                ->map(fn ($total): int => (int) $total)
                ->all(),
            'recentRequests' => RequestActivity::query()
                ->forSource($source)
                ->latest('occurred_at')
                ->limit(8)
                ->get(),
            'recentEvents' => SecurityEvent::query()
                ->forSource($source)
                ->latest('occurred_at')
                ->limit(8)
                ->get(),
        ];
    }

    /** @return array{direction: string, percentage: float|null, label: string} */
    private function comparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'direction' => $current > 0 ? 'new' : 'flat',
                'percentage' => null,
                'label' => $current > 0
                    ? 'New activity vs previous 24h'
                    : 'No activity in either 24h period',
            ];
        }

        $percentage = round((($current - $previous) / $previous) * 100, 1);

        return [
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'flat'),
            'percentage' => $percentage,
            'label' => ($percentage > 0 ? '+' : '').$percentage.'% vs previous 24h',
        ];
    }
}
