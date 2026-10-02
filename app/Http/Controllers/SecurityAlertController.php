<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\IpManagementService;
use App\Services\Security\IpNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityAlertController extends Controller
{
    public function index(Request $request): View
    {
        $query = SecurityAlert::query()->with(['acknowledgedBy', 'assignedAdministrator', 'incident', 'securityEvent']);
        $this->applyFilters($query, $request);

        $alerts = $query->orderByDesc('occurred_at')->paginate(15)->appends($request->query());

        $summary = [
            'new' => SecurityAlert::query()->where('status', 'new')->count(),
            'critical' => SecurityAlert::query()->where('severity', 'Critical')->count(),
            'high' => SecurityAlert::query()->where('severity', 'High')->count(),
            'acknowledged' => SecurityAlert::query()->where('status', 'acknowledged')->count(),
            'investigating' => SecurityAlert::query()->where('status', 'investigating')->count(),
            'resolved' => SecurityAlert::query()->where('status', 'resolved')->count(),
        ];

        return view('alerts.index', [
            'alerts' => $alerts,
            'summary' => $summary,
            'filters' => $request->all(),
            'admins' => User::query()->where('role', 'administrator')->orderBy('name')->get(),
        ]);
    }

    public function show(SecurityAlert $alert): View
    {
        $alert->load(['acknowledgedBy', 'assignedAdministrator', 'incident', 'securityEvent', 'remarks.author']);

        $ipDecision = null;
        $ipRules = collect();

        if ($alert->source_ip) {
            $manager = app(IpManagementService::class);
            $ipDecision = $manager->decide($alert->source_ip, recordMatch: false)['decision'];
            $ipRules = $manager->matchingRules($alert->source_ip);
        }

        return view('alerts.show', [
            'alert' => $alert,
            'ipDecision' => $ipDecision,
            'ipRules' => $ipRules,
            'admins' => User::query()->where('role', 'administrator')->orderBy('name')->get(),
            'openIncidents' => Incident::query()->forSource($alert->source)->whereIn('status', ['open', 'investigating', 'contained'])->orderByDesc('last_detected_at')->get(),
            'relatedAlerts' => $alert->source_ip
                ? SecurityAlert::query()->forSource($alert->source)->where('source_ip', $alert->source_ip)->whereKeyNot($alert->id)
                    ->where('occurred_at', '>=', now()->subDay())->latest('occurred_at')->limit(10)->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Admin middleware enforces authorization

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'alert_type' => ['nullable', 'string', 'max:80'],
            'severity' => ['required', 'in:Normal,Warning,Suspicious,High,Critical'],
            'description' => ['nullable', 'string'],
            'security_event_id' => ['nullable', 'exists:security_events,id'],
            'source_ip' => ['nullable', 'ip'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $alert = DB::transaction(function () use ($validated, $request) {
            $source = isset($validated['security_event_id'])
                ? SecurityEvent::query()->whereKey($validated['security_event_id'])->value('source')
                : config('intsec.source', 'intsec');
            $a = SecurityAlert::query()->create([
                'alert_id' => SecurityAlert::generateAlertId(),
                'source' => $source ?? config('intsec.source', 'intsec'),
                'title' => $validated['title'],
                'alert_type' => $validated['alert_type'] ?? null,
                'severity' => $validated['severity'],
                'description' => $validated['description'] ?? null,
                'security_event_id' => $validated['security_event_id'] ?? null,
                'source_ip' => $validated['source_ip'] ?? null,
                'status' => 'new',
                'occurred_at' => $validated['occurred_at'] ?? now(),
            ]);

            AuditLog::record(
                'alert_created',
                'security_alert',
                $a->alert_id,
                $a->id,
                null,
                [
                    'title' => $a->title,
                    'severity' => $a->severity,
                    'status' => $a->status,
                ],
                'Security alert created.',
                $request->ip(),
                $request->user(),
            );

            return $a;
        });

        return redirect()->route('alerts.show', $alert)->with('status', 'alert-created');
    }

    public function acknowledge(Request $request, SecurityAlert $alert): RedirectResponse
    {
        // Admin middleware enforces authorization

        $previous = $alert->status;
        $alert->status = 'acknowledged';
        $alert->acknowledged_by = $request->user()->id;
        $alert->acknowledged_at = now();
        $alert->save();

        AuditLog::record(
            'alert_acknowledged',
            'security_alert',
            $alert->alert_id,
            $alert->id,
            ['status' => $previous],
            ['status' => $alert->status],
            'Alert was acknowledged by an administrator.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('alerts.show', $alert)->with('status', 'acknowledged');
    }

    public function updateStatus(Request $request, SecurityAlert $alert): RedirectResponse
    {
        // Admin middleware enforces authorization

        $validated = $request->validate([
            'status' => ['required', 'in:new,acknowledged,investigating,resolved,dismissed'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $previous = $alert->status;
        $alert->status = $validated['status'];
        $alert->save();

        AuditLog::record(
            'alert_status_updated',
            'security_alert',
            $alert->alert_id,
            $alert->id,
            ['status' => $previous],
            ['status' => $alert->status, 'reason' => $validated['reason'] ?? null],
            'Alert status updated.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('alerts.show', $alert)->with('status', 'status-updated');
    }

    public function updateSeverity(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $validated = $request->validate(['severity' => ['required', 'in:Normal,Warning,Suspicious,High,Critical']]);
        $previous = $alert->severity;
        $alert->update(['severity' => $validated['severity']]);

        AuditLog::record('alert_severity_updated', 'security_alert', $alert->alert_id, $alert->id,
            ['severity' => $previous], ['severity' => $alert->severity], 'Alert severity was updated.', $request->ip(), $request->user());

        return back()->with('status', 'severity-updated');
    }

    public function assign(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $validated = $request->validate(['assigned_to' => ['required', 'exists:users,id']]);
        $administrator = User::query()->whereKey($validated['assigned_to'])->where('role', 'administrator')->firstOrFail();
        $previous = $alert->assigned_to;
        $alert->update(['assigned_to' => $administrator->id, 'assigned_at' => now()]);

        AuditLog::record('alert_assigned', 'security_alert', $alert->alert_id, $alert->id,
            ['assigned_to' => $previous], ['assigned_to' => $administrator->id], 'Alert assigned to an administrator.', $request->ip(), $request->user());

        return back()->with('status', 'alert-assigned');
    }

    public function storeRemark(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $validated = $request->validate(['remark' => ['required', 'string', 'max:2000']]);
        $alert->remarks()->create(['author_id' => $request->user()->id, 'remark' => $validated['remark']]);

        AuditLog::record('alert_remark_added', 'security_alert', $alert->alert_id, $alert->id,
            null, ['remark' => $validated['remark']], 'Alert investigation remark added.', $request->ip(), $request->user());

        return back()->with('status', 'remark-added');
    }

    public function markFalsePositive(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $previous = $alert->status;
        $alert->update(['status' => 'dismissed']);
        $alert->remarks()->create(['author_id' => $request->user()->id, 'remark' => 'Marked as false positive: '.$validated['reason']]);

        AuditLog::record('alert_false_positive', 'security_alert', $alert->alert_id, $alert->id,
            ['status' => $previous], ['status' => 'dismissed', 'reason' => $validated['reason']], 'Alert marked as a false positive.', $request->ip(), $request->user());

        return back()->with('status', 'false-positive');
    }

    public function attachIncident(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $validated = $request->validate(['incident_id' => ['required', 'exists:incidents,id']]);
        $incident = Incident::query()->whereKey($validated['incident_id'])->whereIn('status', ['open', 'investigating', 'contained'])->firstOrFail();
        $previous = $alert->incident_id;
        $alert->update(['incident_id' => $incident->id]);
        $incident->remarks()->create(['author_id' => $request->user()->id, 'remark' => 'Alert '.$alert->alert_id.' attached to this incident.']);

        AuditLog::record('alert_attached_to_incident', 'security_alert', $alert->alert_id, $alert->id,
            ['incident_id' => $previous], ['incident_id' => $incident->id], 'Alert attached to an existing incident.', $request->ip(), $request->user());

        return redirect()->route('incidents.show', $incident)->with('status', 'alert-attached');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'alert_ids' => ['required', 'array', 'min:1'],
            'alert_ids.*' => ['integer', 'exists:security_alerts,id'],
            'bulk_action' => ['required', 'in:acknowledge,dismiss'],
        ]);

        $status = $validated['bulk_action'] === 'acknowledge' ? 'acknowledged' : 'dismissed';
        $alerts = SecurityAlert::query()->whereIn('id', $validated['alert_ids'])->get();

        foreach ($alerts as $alert) {
            $alert->update(array_filter([
                'status' => $status,
                'acknowledged_by' => $status === 'acknowledged' ? $request->user()->id : null,
                'acknowledged_at' => $status === 'acknowledged' ? now() : null,
            ], fn ($value) => $value !== null));
        }

        AuditLog::record('alerts_bulk_'.$validated['bulk_action'], 'security_alert', 'Selected alerts', null,
            null, ['alert_ids' => $alerts->pluck('id')->all(), 'status' => $status], 'Bulk alert status update.', $request->ip(), $request->user());

        return back()->with('status', 'alerts-bulk-updated');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SecurityAlert::query()->with(['assignedAdministrator', 'incident']);
        $this->applyFilters($query, $request);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Alert ID', 'Title', 'Detection', 'Severity', 'Status', 'Source IP', 'Assignee', 'Incident', 'Occurred At']);
            $query->orderByDesc('occurred_at')->chunkById(200, function ($alerts) use ($handle): void {
                foreach ($alerts as $alert) {
                    fputcsv($handle, [$alert->alert_id, $alert->title, $alert->typeLabel(), $alert->severity, $alert->status, $alert->source_ip,
                        $alert->assignedAdministrator?->name, $alert->incident?->incident_id, $alert->occurred_at?->toDateTimeString()]);
                }
            });
            fclose($handle);
        }, 'security-alerts.csv', ['Content-Type' => 'text/csv']);
    }

    public function createIncident(Request $request, SecurityAlert $alert): RedirectResponse
    {
        // Admin middleware enforces authorization

        if ($alert->incident_id) {
            return redirect()->route('alerts.show', $alert)->with('status', 'incident-exists');
        }

        $incident = DB::transaction(function () use ($alert, $request) {
            $inc = Incident::query()->create([
                'source' => $alert->source,
                'title' => $alert->title,
                'description' => $alert->description ?? 'Created from alert '.$alert->alert_id,
                'incident_type' => $alert->alert_type ?? 'security_alert',
                'severity' => $alert->severity,
                'status' => 'open',
                'source_ip' => $alert->source_ip,
                'user_id' => $alert->securityEvent?->user_id ?? null,
                'security_event_id' => $alert->security_event_id,
                'assigned_to' => $request->user()->id,
                'assigned_at' => now(),
                'detection_reason' => 'Converted from alert '.$alert->alert_id,
                'event_count' => 1,
                'first_detected_at' => $alert->occurred_at ?? now(),
                'last_detected_at' => $alert->occurred_at ?? now(),
            ]);

            $inc->remarks()->create([
                'author_id' => $request->user()->id,
                'remark' => 'Incident opened from alert '.$alert->alert_id,
            ]);

            $alert->incident_id = $inc->id;
            $alert->save();

            AuditLog::record(
                'alert_converted_to_incident',
                'security_alert',
                $alert->alert_id,
                $alert->id,
                null,
                ['incident_id' => $inc->id, 'incident_identifier' => $inc->incident_id],
                'Alert was converted to an incident.',
                $request->ip(),
                $request->user(),
            );

            return $inc;
        });

        return redirect()->route('incidents.show', $incident)->with('status', 'incident-created');
    }

    /**
     * Alert -> IP Management: create a BLOCK rule for the alert source IP.
     * Idempotent: reuses the existing enforcing block rule when covered.
     */
    public function blockIp(Request $request, SecurityAlert $alert, IpManagementService $ipManagement): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'expiration' => ['nullable', 'in:permanent,30m,1h,24h,7d,30d,custom'],
            'confirm_self_block' => ['nullable', 'boolean'],
        ]);

        $sourceIp = trim((string) $alert->source_ip);

        if ($sourceIp === '') {
            return redirect()->route('alerts.show', $alert)->withErrors(['source_ip' => 'This alert has no source IP to block.']);
        }

        if (IpNetwork::matches($sourceIp, $request->ip() ?? '') && ! $request->boolean('confirm_self_block')) {
            return back()->withErrors([
                'source_ip' => 'Blocking '.$sourceIp.' would block your own IP address ('.$request->ip().'). Confirm explicitly to proceed.',
            ]);
        }

        try {
            $rule = $ipManagement->blockFromAlert($alert, [
                'reason' => $validated['reason'] ?? null,
                'expires_at' => $this->resolveBlockExpiration($validated),
                'incident_id' => $alert->incident_id,
            ], $request->user(), $request->ip());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['source_ip' => $e->getMessage()]);
        }

        return redirect()->route('ip-management.index', ['search' => $rule->ip_address])
            ->with('status', 'alert-ip-blocked');
    }

    protected function resolveBlockExpiration(array $validated): mixed
    {
        $mode = $validated['expiration'] ?? null;

        if ($mode === null || $mode === 'permanent') {
            return $validated['expires_at'] ?? null;
        }

        if ($mode === 'custom') {
            return $validated['expires_at'] ?? null;
        }

        return match ($mode) {
            '30m' => now()->addMinutes(30),
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            default => null,
        };
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($nested) use ($search): void {
                $nested->where('alert_id', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('alert_type', 'like', '%'.$search.'%')
                    ->orWhere('source_ip', 'like', '%'.$search.'%');
            });
        }

        foreach (['source', 'severity', 'status', 'alert_type', 'assigned_to'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('source_ip')) {
            $query->where('source_ip', 'like', '%'.trim((string) $request->input('source_ip')).'%');
        }

        $preset = $request->input('range');
        if (in_array($preset, ['today', '7d', '30d'], true)) {
            $query->where('occurred_at', '>=', match ($preset) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('occurred_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('occurred_at', '<=', $request->input('to_date'));
        }
    }
}
