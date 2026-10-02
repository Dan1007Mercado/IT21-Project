<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringSource;
use App\Models\AuthenticationLog;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function overview(string $source): View
    {
        $monitoringSource = $this->source($source);

        return view('monitoring.overview', [
            'monitoringSource' => $monitoringSource,
            'requestCount' => RequestActivity::query()->forSource($source)->count(),
            'authenticationCount' => AuthenticationLog::query()->forSource($source)->count(),
            'securityEventCount' => SecurityEvent::query()->forSource($source)->count(),
            'openAlertCount' => SecurityAlert::query()->forSource($source)->whereIn('status', ['new', 'acknowledged', 'investigating'])->count(),
            'openIncidentCount' => Incident::query()->forSource($source)->whereIn('status', ['open', 'investigating', 'contained'])->count(),
            'recentRequests' => RequestActivity::query()->forSource($source)->latest('occurred_at')->limit(8)->get(),
            'recentEvents' => SecurityEvent::query()->forSource($source)->latest('occurred_at')->limit(8)->get(),
        ]);
    }

    public function requestActivities(Request $request, string $source): View
    {
        $monitoringSource = $this->source($source);
        $query = RequestActivity::query()->forSource($source);

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
            'activities' => $query->latest('occurred_at')->paginate(25)->appends($request->query()),
        ]);
    }

    public function securityEvents(Request $request, string $source): View
    {
        $monitoringSource = $this->source($source);
        $query = SecurityEvent::query()->forSource($source);

        foreach (['event_type', 'severity', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return view('monitoring.security-events', [
            'monitoringSource' => $monitoringSource,
            'events' => $query->latest('occurred_at')->paginate(25)->appends($request->query()),
        ]);
    }

    private function source(string $source): MonitoringSource
    {
        return MonitoringSource::tryFrom($source) ?? abort(404);
    }
}
