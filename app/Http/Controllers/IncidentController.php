<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentRemark;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Incident::query()->with(['user', 'assignedAdministrator', 'securityEvent']);

        $this->applyFilters($query, $request);

        $incidents = $query
            ->orderByDesc('last_detected_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        $summary = [
            'open' => Incident::query()->where('status', 'open')->count(),
            'investigating' => Incident::query()->where('status', 'investigating')->count(),
            'high_critical' => Incident::query()->whereIn('severity', ['High', 'Critical'])->count(),
            'contained' => Incident::query()->where('status', 'contained')->count(),
            'resolved' => Incident::query()->where('status', 'resolved')->count(),
        ];

        return view('incidents.index', [
            'incidents' => $incidents,
            'summary' => $summary,
            'filters' => $request->all(),
        ]);
    }

    public function show(Incident $incident): View
    {
        $incident->load(['user', 'assignedAdministrator', 'securityEvent', 'remarks.author', 'statusHistory.actor']);

        return view('incidents.show', [
            'incident' => $incident,
            'timeline' => $incident->timelineEntries(),
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'incident_type' => ['required', 'string', 'max:80'],
            'source_ip' => ['nullable', 'ip'],
            'user_id' => ['nullable', 'exists:users,id'],
            'detection_reason' => ['nullable', 'string'],
        ]);

        $previous = [
            'title' => $incident->title,
            'description' => $incident->description,
            'incident_type' => $incident->incident_type,
            'source_ip' => $incident->source_ip,
            'user_id' => $incident->user_id,
            'detection_reason' => $incident->detection_reason,
        ];

        $incident->fill($validated);
        $incident->save();

        AuditLog::record(
            'incident_updated',
            'incident',
            $incident->incident_id,
            $incident->id,
            $previous,
            [
                'title' => $incident->title,
                'description' => $incident->description,
                'incident_type' => $incident->incident_type,
                'source_ip' => $incident->source_ip,
                'user_id' => $incident->user_id,
                'detection_reason' => $incident->detection_reason,
            ],
            'Incident details were updated by an administrator.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident)->with('status', 'incident-updated');
    }

    public function updateSeverity(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'severity' => ['required', 'in:Normal,Warning,Suspicious,High,Critical'],
        ]);

        $previous = $incident->severity;
        $incident->severity = $validated['severity'];
        $incident->save();

        AuditLog::record(
            'incident_severity_changed',
            'incident',
            $incident->incident_id,
            $incident->id,
            ['severity' => $previous],
            ['severity' => $validated['severity']],
            'Incident severity was updated by an administrator.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident)->with('status', 'severity-updated');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'incident_type' => ['required', 'string', 'max:80'],
            'severity' => ['required', 'in:Normal,Warning,Suspicious,High,Critical'],
            'status' => ['required', 'in:open,investigating,contained,resolved,false_positive'],
            'source_ip' => ['nullable', 'ip'],
            'user_id' => ['nullable', 'exists:users,id'],
            'security_event_id' => ['nullable', 'exists:security_events,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'detection_reason' => ['nullable', 'string'],
            'detection_rule' => ['nullable', 'string', 'max:120'],
            'event_count' => ['nullable', 'integer', 'min:0'],
            'first_detected_at' => ['nullable', 'date'],
            'last_detected_at' => ['nullable', 'date'],
            'response_actions' => ['nullable', 'string'],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $incident = DB::transaction(function () use ($validated, $request) {
            $incident = Incident::query()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'incident_type' => $validated['incident_type'],
                'severity' => $validated['severity'],
                'status' => $validated['status'],
                'source_ip' => $validated['source_ip'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'security_event_id' => $validated['security_event_id'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? $request->user()?->id,
                'assigned_at' => $validated['assigned_to'] ? now() : null,
                'detection_reason' => $validated['detection_reason'] ?? null,
                'detection_rule' => $validated['detection_rule'] ?? null,
                'event_count' => $validated['event_count'] ?? 1,
                'first_detected_at' => $validated['first_detected_at'] ?? now(),
                'last_detected_at' => $validated['last_detected_at'] ?? now(),
                'response_actions' => $validated['response_actions'] ?? null,
                'resolution_notes' => $validated['resolution_notes'] ?? null,
            ]);

            $incident->remarks()->create([
                'author_id' => $request->user()->id,
                'remark' => 'Incident opened and assigned for investigation.',
            ]);

            AuditLog::record(
                'incident_created',
                'incident',
                $incident->incident_id,
                $incident->id,
                null,
                [
                    'title' => $incident->title,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                    'source_ip' => $incident->source_ip,
                ],
                'Incident opened by an administrator.',
                $request->ip(),
                $request->user(),
            );

            return $incident;
        });

        return redirect()->route('incidents.show', $incident)->with('status', 'incident-created');
    }

    public function storeRemark(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        $incident->remarks()->create([
            'author_id' => $request->user()->id,
            'remark' => $validated['remark'],
        ]);

        AuditLog::record(
            'incident_remark_added',
            'incident',
            $incident->incident_id,
            $incident->id,
            null,
            ['remark' => $validated['remark']],
            'Investigation remark added by an administrator.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident)->with('status', 'remark-added');
    }

    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,investigating,contained,resolved,false_positive'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $previousStatus = $incident->status;

        DB::transaction(function () use ($incident, $validated, $request, $previousStatus) {
            $incident->updateStatus($validated['status'], $request->user(), $validated['reason'] ?? null);

            if ($validated['status'] === 'contained' && empty($incident->contained_at)) {
                $incident->contained_at = now();
                $incident->save();
            }

            if ($validated['status'] === 'resolved' && empty($incident->resolved_at)) {
                $incident->resolved_at = now();
                $incident->save();
            }

            if ($validated['status'] === 'investigating' && empty($incident->acknowledged_at)) {
                $incident->acknowledged_at = now();
                $incident->save();
            }

            AuditLog::record(
                'incident_status_updated',
                'incident',
                $incident->incident_id,
                $incident->id,
                ['status' => $previousStatus],
                ['status' => $validated['status'], 'reason' => $validated['reason'] ?? null],
                'Incident status was updated by an administrator.',
                $request->ip(),
                $request->user(),
            );
        });

        return redirect()->route('incidents.show', $incident)->with('status', 'status-updated');
    }

    public function assign(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $previous = $incident->assigned_to;
        $incident->assigned_to = $validated['assigned_to'];
        $incident->assigned_at = now();
        $incident->save();

        AuditLog::record(
            'incident_assigned',
            'incident',
            $incident->incident_id,
            $incident->id,
            ['assigned_to' => $previous],
            ['assigned_to' => $validated['assigned_to']],
            'An administrator assigned the incident to a responder.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident)->with('status', 'incident-assigned');
    }

    public function storeResponseAction(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'response_actions' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:open,investigating,contained,resolved,false_positive'],
        ]);

        if (! empty($validated['response_actions'])) {
            $incident->response_actions = $validated['response_actions'];
        }

        if (! empty($validated['resolution_notes'])) {
            $incident->resolution_notes = $validated['resolution_notes'];
        }

        if (! empty($validated['status'])) {
            $this->updateStatus($request->merge(['status' => $validated['status'], 'reason' => 'Administrative response update']), $incident);
        }

        $incident->save();

        AuditLog::record(
            'incident_response_updated',
            'incident',
            $incident->incident_id,
            $incident->id,
            null,
            [
                'response_actions' => $incident->response_actions,
                'resolution_notes' => $incident->resolution_notes,
            ],
            'Incident response details were recorded.',
            $request->ip(),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident)->with('status', 'response-updated');
    }

    protected function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('incident_id', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('source_ip', 'like', '%'.$search.'%')
                    ->orWhere('incident_type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($sub) => $sub->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('assignedAdministrator', fn ($sub) => $sub->where('name', 'like', '%'.$search.'%'));
            });
        }

        $filters = [
            'incident_id' => 'incident_id',
            'source_ip' => 'source_ip',
            'user_id' => 'user_id',
            'incident_type' => 'incident_type',
            'severity' => 'severity',
            'status' => 'status',
            'assigned_to' => 'assigned_to',
        ];

        foreach ($filters as $inputName => $column) {
            if ($request->filled($inputName)) {
                $query->where($column, $request->input($inputName));
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('first_detected_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('first_detected_at', '<=', $request->input('to_date'));
        }
    }
}
