<?php

namespace App\Http\Controllers;

use App\Models\AuthenticationLog;
use App\Models\AuditLog;
use App\Models\SecurityAlert;
use App\Models\Incident;
use App\Services\Security\IntsecSettings;
use App\Services\Security\IpActivityIncidentService;
use App\Services\Security\IpWhoisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $activityLogs = $user->authenticationLogs();

        $range = in_array($request->query('range'), ['today', '7d', '30d', '90d'], true)
            ? $request->query('range')
            : '7d';
        $activityTrend = $this->buildActivityTrend($activityLogs, $range);
        $statusBreakdown = [
            'successful' => (clone $activityLogs)->where('status', 'successful')->count(),
            'failed' => (clone $activityLogs)->where('status', 'failed')->count(),
            'logout' => (clone $activityLogs)->where('action', 'logout')->where('status', 'successful')->count(),
        ];

        $attackFrequency = $this->buildAttackFrequency()->take(12)->values()->all();
        $ipLocations = $this->prepareIpLocations();
        $isAdministrator = $user->isAdministrator();
        $openSecurityAlerts = $isAdministrator
            ? SecurityAlert::query()
                ->whereIn('status', ['new', 'acknowledged', 'investigating'])
                ->latest('occurred_at')
            : collect();
        $severityDistribution = $isAdministrator
            ? SecurityAlert::query()->selectRaw('severity, COUNT(*) as total')->whereIn('status', ['new', 'acknowledged', 'investigating'])
                ->groupBy('severity')->pluck('total', 'severity')->all()
            : [];
        $needsAttention = $isAdministrator ? [
            'alerts' => SecurityAlert::query()->with('assignedAdministrator')->whereNull('assigned_to')
                ->whereIn('severity', ['Critical', 'High'])->whereIn('status', ['new', 'acknowledged', 'investigating'])->latest('occurred_at')->limit(5)->get(),
            'incidents' => Incident::query()->whereIn('status', ['open', 'investigating', 'contained'])
                ->where('last_detected_at', '<=', now()->subDay())->latest('last_detected_at')->limit(5)->get(),
        ] : ['alerts' => collect(), 'incidents' => collect()];
        $acknowledgementSeconds = $isAdministrator ? SecurityAlert::query()->whereNotNull('acknowledged_at')->get(['occurred_at', 'acknowledged_at'])
            ->avg(fn (SecurityAlert $alert) => $alert->occurred_at?->diffInSeconds($alert->acknowledged_at)) : null;
        $resolutionSeconds = $isAdministrator ? Incident::query()->whereNotNull('resolved_at')->get(['first_detected_at', 'resolved_at'])
            ->avg(fn (Incident $incident) => $incident->first_detected_at?->diffInSeconds($incident->resolved_at)) : null;

        return view('dashboard', [
            'successfulLogins' => (clone $activityLogs)
                ->where('action', 'login')
                ->where('status', 'successful')
                ->count(),
            'failedAttempts' => AuthenticationLog::query()
                ->where('attempted_identity', $user->email)
                ->where('action', 'login')
                ->where('status', 'failed')
                ->count(),
            'recentActivity' => (clone $activityLogs)
                ->latest('occurred_at')
                ->limit(5)
                ->get(),
            'activityTrend' => $activityTrend,
            'activityRange' => $range,
            'statusBreakdown' => $statusBreakdown,
            'attackFrequency' => $attackFrequency,
            'ipLocations' => array_slice($ipLocations, 0, 12),
            'isAdministrator' => $isAdministrator,
            'openSecurityAlertCount' => $isAdministrator ? (clone $openSecurityAlerts)->count() : 0,
            'recentSecurityAlerts' => $isAdministrator ? $openSecurityAlerts->limit(3)->get() : collect(),
            'alertSeverityDistribution' => $severityDistribution,
            'topAttackingIps' => array_slice($attackFrequency, 0, 5),
            'needsAttention' => $needsAttention,
            'averageAcknowledgementSeconds' => $acknowledgementSeconds,
            'averageResolutionSeconds' => $resolutionSeconds,
        ]);
    }

    public function ipLocations(Request $request): View
    {
        $query = AuthenticationLog::query()
            ->selectRaw('ip_address, MAX(country) as country, MAX(country_code) as country_code, MAX(region) as region, MAX(region_code) as region_code, MAX(city) as city, MAX(latitude) as latitude, MAX(longitude) as longitude, MAX(postal) as postal, MAX(isp) as isp, MAX(organization) as organization, MAX(asn) as asn, MAX(timezone) as timezone, COUNT(*) as event_count, MAX(occurred_at) as last_seen')
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->groupBy('ip_address')
            ->orderByDesc('event_count');

        $paginator = $query->paginate(10);
        $paginator->setCollection($paginator->getCollection()->map(function ($entry) {
            return [
                'ip' => $entry->ip_address,
                'country' => $entry->country,
                'country_code' => $entry->country_code,
                'region' => $entry->region,
                'region_code' => $entry->region_code,
                'city' => $entry->city,
                'latitude' => $entry->latitude,
                'longitude' => $entry->longitude,
                'postal' => $entry->postal,
                'isp' => $entry->isp,
                'organization' => $entry->organization,
                'asn' => $entry->asn,
                'timezone' => $entry->timezone,
                'event_count' => (int) $entry->event_count,
                'last_seen' => $entry->last_seen,
            ];
        }));

        $countryCount = collect($paginator->items())
            ->map(fn ($location) => $location['country'] ?? null)
            ->filter()
            ->unique()
            ->count();

        $cityCount = collect($paginator->items())
            ->map(fn ($location) => $location['city'] ?? null)
            ->filter()
            ->unique()
            ->count();

        return view('ip-locations.index', [
            'ipLocations' => $paginator,
            'countryCount' => $countryCount,
            'cityCount' => $cityCount,
            'mapLocations' => $paginator->items(),
        ]);
    }

    public function ddosMonitoring(Request $request): View
    {
        $hourlyTrend = collect(range(0, 23))->map(function (int $hour) {
            return [
                'label' => sprintf('%02d:00', $hour),
                'count' => 0,
            ];
        })->values();

        $bucketCounts = AuthenticationLog::query()
            ->whereNotNull('occurred_at')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->get(['occurred_at'])
            ->groupBy(function ($log) {
                return $log->occurred_at->hour;
            });

        foreach ($bucketCounts as $hour => $logs) {
            $hourlyTrend[$hour] = [
                'label' => sprintf('%02d:00', $hour),
                'count' => $logs->count(),
            ];
        }

        $currentRequests = $hourlyTrend->last()['count'] ?? 0;
        $peakRequests = $hourlyTrend->max(fn ($entry) => (int) $entry['count']) ?? 0;
        $threshold = IntsecSettings::getInt('repeated_ip_activity_threshold', 10);
        $suspiciousSpikes = $hourlyTrend->filter(fn ($entry) => (int) $entry['count'] > $threshold)->count();

        return view('ddos-monitoring.index', [
            'hourlyTrend' => $hourlyTrend,
            'currentRequests' => $currentRequests,
            'peakRequests' => $peakRequests,
            'suspiciousSpikes' => $suspiciousSpikes,
        ]);
    }

    public function attackFrequency(Request $request, IpActivityIncidentService $ipActivityIncidentService): View
    {
        $attackFrequency = AuthenticationLog::query()
            ->selectRaw('ip_address, COUNT(*) as request_count')
            ->whereNotNull('ip_address')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->groupBy('ip_address')
            ->orderByDesc('request_count')
            ->paginate(10);

        $attackFrequency->setCollection($attackFrequency->getCollection()->map(function ($entry) {
            return [
                'ip' => $entry->ip_address,
                'count' => (int) $entry->request_count,
            ];
        }));

        // Process each IP for threshold-based alerting/incidents (idempotent)
        $threshold = IntsecSettings::getInt('repeated_ip_activity_threshold', 10);

        foreach ($attackFrequency->getCollection() as $row) {
            try {
                if (($row['count'] ?? 0) > $threshold) {
                    $ipActivityIncidentService->evaluate($row['ip'], $row['count'], $threshold, $request->user(), $request->ip());
                }
            } catch (\Throwable $e) {
                // Do not interrupt page rendering on error; log audit entry
                AuditLog::record('alert_processing_error', 'attack_frequency', $row['ip'], null, null, ['error' => $e->getMessage()], 'Error processing automatic alert for IP', $request->ip(), $request->user());
            }
        }

        return view('attack-frequency.index', [
            'attackFrequency' => $attackFrequency,
        ]);
    }

    public function loginActivity(Request $request): View
    {
        return view('login-activity', [
            'logs' => $request->user()
                ->authenticationLogs()
                ->latest('occurred_at')
                ->paginate(10),
        ]);
    }

    protected function buildActivityTrend($activityLogs, string $range): array
    {
        if ($range === 'today') {
            return collect(range(0, 23))->map(function (int $hour) use ($activityLogs) {
                return ['label' => sprintf('%02d:00', $hour), 'count' => (clone $activityLogs)->whereDate('occurred_at', now()->toDateString())->get()->filter(fn ($log) => $log->occurred_at?->hour === $hour)->count()];
            })->all();
        }

        $days = ['7d' => 7, '30d' => 30, '90d' => 90][$range];

        return collect(range($days - 1, 0))->map(function (int $daysAgo) use ($activityLogs) {
            $date = now()->subDays($daysAgo);

            return [
                'label' => $date->format('M d'),
                'count' => (clone $activityLogs)
                    ->whereDate('occurred_at', $date->toDateString())
                    ->count(),
            ];
        })->values()->all();
    }

    protected function buildAttackFrequency(): \Illuminate\Support\Collection
    {
        return AuthenticationLog::query()
            ->selectRaw('ip_address, COUNT(*) as request_count')
            ->whereNotNull('ip_address')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->groupBy('ip_address')
            ->orderByDesc('request_count')
            ->limit(12)
            ->get()
            ->map(function ($entry) {
                return [
                    'ip' => $entry->ip_address,
                    'count' => (int) $entry->request_count,
                ];
            });
    }

    protected function prepareIpLocations(): array
    {
        $service = app(IpWhoisService::class);
        $publicIps = AuthenticationLog::query()
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->pluck('ip_address')
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values();

        $locations = [];

        foreach ($publicIps as $ip) {
            $existing = AuthenticationLog::query()
                ->where('ip_address', $ip)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->first();

            $resolved = $existing ? [
                'ip' => $ip,
                'country' => $existing->country,
                'country_code' => $existing->country_code,
                'region' => $existing->region,
                'region_code' => $existing->region_code,
                'city' => $existing->city,
                'latitude' => (float) $existing->latitude,
                'longitude' => (float) $existing->longitude,
                'postal' => $existing->postal,
                'isp' => $existing->isp,
                'organization' => $existing->organization,
                'asn' => $existing->asn,
                'timezone' => $existing->timezone,
            ] : $service->lookup($ip);

            if (! is_array($resolved)) {
                continue;
            }

            $query = AuthenticationLog::query()->where('ip_address', $ip);
            $query->update([
                'country' => $resolved['country'] ?? null,
                'country_code' => $resolved['country_code'] ?? null,
                'region' => $resolved['region'] ?? null,
                'region_code' => $resolved['region_code'] ?? null,
                'city' => $resolved['city'] ?? null,
                'latitude' => $resolved['latitude'] ?? null,
                'longitude' => $resolved['longitude'] ?? null,
                'postal' => $resolved['postal'] ?? null,
                'isp' => $resolved['isp'] ?? null,
                'organization' => $resolved['organization'] ?? null,
                'asn' => $resolved['asn'] ?? null,
                'timezone' => $resolved['timezone'] ?? null,
            ]);

            $eventCount = $query->count();
            $lastSeen = $query->max('occurred_at');
            $lastSeenIso = $lastSeen instanceof \Carbon\CarbonInterface
                ? $lastSeen->toIso8601String()
                : (is_string($lastSeen) ? \Carbon\Carbon::parse($lastSeen)->toIso8601String() : null);

            $locations[] = [
                'ip' => $resolved['ip'] ?? $ip,
                'country' => $resolved['country'] ?? null,
                'country_code' => $resolved['country_code'] ?? null,
                'region' => $resolved['region'] ?? null,
                'city' => $resolved['city'] ?? null,
                'latitude' => $resolved['latitude'] ?? null,
                'longitude' => $resolved['longitude'] ?? null,
                'postal' => $resolved['postal'] ?? null,
                'isp' => $resolved['isp'] ?? null,
                'organization' => $resolved['organization'] ?? null,
                'asn' => $resolved['asn'] ?? null,
                'timezone' => $resolved['timezone'] ?? null,
                'event_count' => $eventCount,
                'last_seen' => $lastSeenIso,
            ];
        }

        usort($locations, fn ($left, $right) => ($right['event_count'] ?? 0) <=> ($left['event_count'] ?? 0));

        return $locations;
    }
}
