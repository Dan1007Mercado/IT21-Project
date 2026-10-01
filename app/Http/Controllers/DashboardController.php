<?php

namespace App\Http\Controllers;

use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\IpIntelligence;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\IntsecSettings;
use App\Services\Security\RequestActivityQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private RequestActivityQueryService $requestQueries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdministrator = $user->isAdministrator();
        $range = in_array($request->query('range'), ['today', '7d', '30d', '90d'], true)
            ? $request->query('range')
            : '7d';
        $days = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90][$range];

        $authenticationQuery = AuthenticationLog::query()->where('user_id', $user->id);
        $statusBreakdown = [
            'successful' => (clone $authenticationQuery)->where('action', 'login')->where('status', 'successful')->count(),
            'failed' => AuthenticationLog::query()->where('attempted_identity', $user->email)
                ->where('action', 'login')->where('status', 'failed')->count(),
            'logout' => (clone $authenticationQuery)->where('action', 'logout')->where('status', 'successful')->count(),
        ];

        $topActiveIps = RequestActivity::query()
            ->whereNotNull('ip_address')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->selectRaw('ip_address, COUNT(*) as request_count, MAX(occurred_at) as last_seen')
            ->groupBy('ip_address')
            ->orderByDesc('request_count')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'isAdministrator' => $isAdministrator,
            'activityRange' => $range,
            'successfulLogins' => $statusBreakdown['successful'],
            'failedAttempts' => $statusBreakdown['failed'],
            'statusBreakdown' => $statusBreakdown,
            'recentActivity' => (clone $authenticationQuery)->latest('occurred_at')->limit(5)->get(),
            'authenticationTrend' => $this->authenticationTrend($user->id, $days),
            'requestTrend' => $this->requestQueries->dailyTrend($days),
            'totalSecurityEvents' => $isAdministrator ? SecurityEvent::query()->count() : 0,
            'openSecurityAlertCount' => $isAdministrator ? SecurityAlert::query()->whereIn('status', ['new', 'acknowledged', 'investigating'])->count() : 0,
            'openIncidentCount' => $isAdministrator ? Incident::query()->whereIn('status', ['open', 'investigating', 'contained'])->count() : 0,
            'blockedIpCount' => $isAdministrator ? BlockedIp::query()->enforcing()->where('action', 'block')->count() : 0,
            'recentSecurityAlerts' => $isAdministrator
                ? SecurityAlert::query()->with('assignedAdministrator')->whereIn('status', ['new', 'acknowledged', 'investigating'])->latest('occurred_at')->limit(5)->get()
                : collect(),
            'recentIncidents' => $isAdministrator
                ? Incident::query()->with('assignedAdministrator')->latest('last_detected_at')->limit(5)->get()
                : collect(),
            'alertSeverityDistribution' => $isAdministrator
                ? SecurityAlert::query()->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity')->all()
                : [],
            'topActiveIps' => $topActiveIps,
            'needsAttention' => $isAdministrator
                ? SecurityAlert::query()->whereNull('assigned_to')->whereIn('severity', ['Critical', 'High'])
                    ->whereIn('status', ['new', 'acknowledged', 'investigating'])->latest('occurred_at')->limit(5)->get()
                : collect(),
        ]);
    }

    public function ipLocations(Request $request): View
    {
        $query = RequestActivity::query()
            ->leftJoin('ip_intelligences', 'ip_intelligences.ip_address', '=', 'request_activities.ip_address')
            ->whereNotNull('request_activities.ip_address')
            ->selectRaw('request_activities.ip_address, MAX(request_activities.ip_type) as ip_type, COUNT(*) as event_count, MAX(request_activities.occurred_at) as last_seen, ip_intelligences.country, ip_intelligences.country_code, ip_intelligences.region, ip_intelligences.city, ip_intelligences.latitude, ip_intelligences.longitude, ip_intelligences.isp, ip_intelligences.organization, ip_intelligences.asn, ip_intelligences.timezone, ip_intelligences.last_enriched_at')
            ->groupBy(
                'request_activities.ip_address', 'ip_intelligences.country', 'ip_intelligences.country_code',
                'ip_intelligences.region', 'ip_intelligences.city', 'ip_intelligences.latitude',
                'ip_intelligences.longitude', 'ip_intelligences.isp', 'ip_intelligences.organization',
                'ip_intelligences.asn', 'ip_intelligences.timezone', 'ip_intelligences.last_enriched_at',
            );

        if ($request->filled('ip')) {
            $query->where('request_activities.ip_address', 'like', '%'.trim((string) $request->input('ip')).'%');
        }
        if ($request->filled('ip_type')) {
            $query->where('request_activities.ip_type', $request->input('ip_type'));
        }
        if ($request->filled('country')) {
            $query->where('ip_intelligences.country_code', $request->input('country'));
        }

        $paginator = $query->orderByDesc('event_count')->paginate(15)->appends($request->query());
        $ips = $paginator->getCollection()->pluck('ip_address')->filter()->values();
        $blocked = $this->requestQueries->blockedStates($ips);

        $paginator->setCollection($paginator->getCollection()->map(fn ($entry): array => [
            'ip' => $entry->ip_address,
            'ip_type' => $entry->ip_type,
            'country' => $entry->country,
            'country_code' => $entry->country_code,
            'region' => $entry->region,
            'city' => $entry->city,
            'latitude' => $entry->latitude === null ? null : (float) $entry->latitude,
            'longitude' => $entry->longitude === null ? null : (float) $entry->longitude,
            'isp' => $entry->isp,
            'organization' => $entry->organization,
            'asn' => $entry->asn,
            'timezone' => $entry->timezone,
            'event_count' => (int) $entry->event_count,
            'last_seen' => $entry->last_seen,
            'last_enriched_at' => $entry->last_enriched_at,
            'is_blocked' => (bool) ($blocked[$entry->ip_address] ?? false),
        ]));

        $mapLocations = $paginator->getCollection()
            ->where('ip_type', 'public')
            ->filter(fn (array $entry): bool => $entry['latitude'] !== null && $entry['longitude'] !== null)
            ->values()
            ->all();

        return view('ip-locations.index', [
            'ipLocations' => $paginator,
            'countryCount' => IpIntelligence::query()->whereNotNull('country_code')->distinct('country_code')->count('country_code'),
            'cityCount' => IpIntelligence::query()->whereNotNull('city')->distinct('city')->count('city'),
            'mapLocations' => $mapLocations,
            'countries' => IpIntelligence::query()->whereNotNull('country_code')->orderBy('country')->pluck('country', 'country_code'),
        ]);
    }

    public function ddosMonitoring(Request $request): View
    {
        $hourlyTrend = collect($this->requestQueries->hourlyTrend(24));
        $windowStart = now()->subDay();
        $threshold = IntsecSettings::getInt('request_spike_threshold', 250);

        $statusDistribution = RequestActivity::query()->where('occurred_at', '>=', $windowStart)
            ->selectRaw("CASE WHEN status_code >= 500 THEN '5xx' WHEN status_code >= 400 THEN '4xx' WHEN status_code >= 300 THEN '3xx' WHEN status_code >= 200 THEN '2xx' ELSE '1xx' END as family, COUNT(*) as total")
            ->groupBy('family')->pluck('total', 'family');

        return view('ddos-monitoring.index', [
            'hourlyTrend' => $hourlyTrend,
            'currentRequests' => (int) ($hourlyTrend->last()['count'] ?? 0),
            'peakRequests' => (int) ($hourlyTrend->max('count') ?? 0),
            'suspiciousSpikes' => $hourlyTrend->where('count', '>=', $threshold)->count(),
            'spikeThreshold' => $threshold,
            'statusDistribution' => $statusDistribution,
            'topIps' => RequestActivity::query()->where('occurred_at', '>=', $windowStart)->whereNotNull('ip_address')
                ->selectRaw('ip_address, COUNT(*) as total')->groupBy('ip_address')->orderByDesc('total')->limit(8)->get(),
            'topRoutes' => RequestActivity::query()->where('occurred_at', '>=', $windowStart)
                ->selectRaw('path, COUNT(*) as total')->groupBy('path')->orderByDesc('total')->limit(8)->get(),
            'clientErrorCount' => RequestActivity::query()->where('occurred_at', '>=', $windowStart)->whereBetween('status_code', [400, 499])->count(),
            'serverErrorCount' => RequestActivity::query()->where('occurred_at', '>=', $windowStart)->whereBetween('status_code', [500, 599])->count(),
        ]);
    }

    public function attackFrequency(Request $request): View
    {
        $filters = $request->only(['range', 'ip', 'classification', 'status_family']);
        $start = $this->requestQueries->startForRange($filters['range'] ?? '7d');
        $query = $this->requestQueries->applyFrequencyFilters(RequestActivity::query(), $filters)
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, MAX(ip_type) as ip_type, MAX(classification) as classification, COUNT(*) as request_count, MAX(occurred_at) as last_seen, SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) as client_errors, SUM(CASE WHEN status_code = 403 THEN 1 ELSE 0 END) as forbidden_count, SUM(CASE WHEN status_code = 404 THEN 1 ELSE 0 END) as not_found_count')
            ->groupBy('ip_address');

        if ($request->filled('min_requests') && ctype_digit((string) $request->input('min_requests'))) {
            $query->havingRaw('COUNT(*) >= ?', [(int) $request->input('min_requests')]);
        }

        $paginator = $query->orderByDesc('request_count')->paginate(15)->appends($request->query());
        $ips = $paginator->getCollection()->pluck('ip_address')->filter()->values();
        $paths = $this->requestQueries->mostRequestedPaths($ips, $start);
        $blocked = $this->requestQueries->blockedStates($ips);

        $paginator->setCollection($paginator->getCollection()->map(fn ($entry): array => [
            'ip' => $entry->ip_address,
            'count' => (int) $entry->request_count,
            'last_seen' => $entry->last_seen,
            'route' => $paths[$entry->ip_address] ?? null,
            'client_errors' => (int) $entry->client_errors,
            'forbidden_count' => (int) $entry->forbidden_count,
            'not_found_count' => (int) $entry->not_found_count,
            'ip_type' => $entry->ip_type,
            'classification' => $entry->classification,
            'is_blocked' => (bool) ($blocked[$entry->ip_address] ?? false),
        ]));

        return view('attack-frequency.index', ['attackFrequency' => $paginator, 'filters' => $filters]);
    }

    public function loginActivity(Request $request): View
    {
        $query = AuthenticationLog::query()->with('user');
        if (! $request->user()->isAdministrator()) {
            $query->where(function (Builder $nested) use ($request): void {
                $nested->where('user_id', $request->user()->id)
                    ->orWhere('attempted_identity', $request->user()->email);
            });
        }

        foreach (['status', 'action', 'user_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('identity')) {
            $query->where('attempted_identity', 'like', '%'.trim((string) $request->input('identity')).'%');
        }
        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%'.trim((string) $request->input('ip')).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->input('to'));
        }

        return view('login-activity', [
            'logs' => $query->latest('occurred_at')->paginate(20)->appends($request->query()),
            'users' => $request->user()->isAdministrator()
                ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    /** @return array<int, array{label: string, count: int}> */
    private function authenticationTrend(int $userId, int $days): array
    {
        return collect(range($days - 1, 0))->map(function (int $daysAgo) use ($userId): array {
            $date = now()->subDays($daysAgo);

            return [
                'label' => $date->format('M j'),
                'count' => AuthenticationLog::query()->where('user_id', $userId)
                    ->whereDate('occurred_at', $date->toDateString())->count(),
            ];
        })->values()->all();
    }
}
