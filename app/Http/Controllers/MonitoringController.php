<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringSource;
use App\Models\RequestActivity;
use App\Models\SecurityEvent;
use App\Services\Security\MonitoringOverviewQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __construct(private MonitoringOverviewQueryService $overviewQueries) {}

    public function overview(string $source): View
    {
        $monitoringSource = $this->source($source);

        return view('monitoring.overview', [
            'monitoringSource' => $monitoringSource,
            ...$this->overviewQueries->forSource($source),
        ]);
    }

    public function requestActivities(Request $request, string $source): View
    {
        $monitoringSource = $this->source($source);
        $query = RequestActivity::query()->forSource($source);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($nested) use ($search): void {
                $nested->where('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('path', 'like', '%'.$search.'%')
                    ->orWhere('route_name', 'like', '%'.$search.'%')
                    ->orWhere('method', 'like', '%'.$search.'%')
                    ->orWhere('status_code', 'like', '%'.$search.'%')
                    ->orWhere('request_id', 'like', '%'.$search.'%')
                    ->orWhere('classification', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%'.trim((string) $request->input('ip')).'%');
        }
        if ($request->filled('method')) {
            $query->where('method', strtoupper((string) $request->input('method')));
        }
        if ($request->filled('status_code') && ctype_digit((string) $request->input('status_code'))) {
            $query->where('status_code', (int) $request->input('status_code'));
        }

        return view('monitoring.request-activities', [
            'monitoringSource' => $monitoringSource,
            'activities' => $query->latest('occurred_at')->simplePaginate(10)->withQueryString(),
        ]);
    }

    public function securityEvents(Request $request, string $source): View
    {
        $monitoringSource = $this->source($source);
        $query = SecurityEvent::query()->forSource($source);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($nested) use ($search): void {
                $nested->where('title', 'like', '%'.$search.'%')
                    ->orWhere('event_type', 'like', '%'.$search.'%')
                    ->orWhere('source_ip', 'like', '%'.$search.'%')
                    ->orWhere('external_event_id', 'like', '%'.$search.'%')
                    ->orWhere('metadata->route', 'like', '%'.$search.'%')
                    ->orWhere('metadata->request_id', 'like', '%'.$search.'%');
            });
        }

        foreach (['event_type', 'severity', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return view('monitoring.security-events', [
            'monitoringSource' => $monitoringSource,
            'events' => $query->latest('occurred_at')->simplePaginate(10)->withQueryString(),
        ]);
    }

    private function source(string $source): MonitoringSource
    {
        return MonitoringSource::tryFrom($source) ?? abort(404);
    }
}
