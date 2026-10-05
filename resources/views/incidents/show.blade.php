<x-layouts.app title="Incident {{ $incident->incident_id }} - INTSEC" realtime-entities="incident,security_alert">
    <div class="ops-page">
        <x-ui.page-header kicker="Incident investigation" :title="$incident->title" :description="$incident->incident_id">
            <x-slot:context><x-security.source-badge :source="$incident->source" /><x-security.severity-badge :severity="$incident->severity" /><x-security.status-badge :status="$incident->status" /></x-slot:context>
            <x-slot:actions><a class="ops-button ops-button--quiet" href="{{ route('incidents.index') }}">Back to incidents</a></x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))<div class="ops-banner ops-banner--success" role="status">Incident workflow updated successfully.</div>@endif
        @if ($errors->any())<div class="ops-banner ops-banner--danger" role="alert"><div><strong>The update could not be saved.</strong><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

        <div class="ops-split-layout">
            <div class="ops-stack">
                <section class="ops-panel ops-panel--padded" aria-labelledby="incident-evidence-title">
                    <div class="ops-panel-header"><div><h2 id="incident-evidence-title" class="ops-panel-title">Incident evidence</h2><p class="ops-panel-description">Core case facts and originating detection context.</p></div></div>
                    <dl class="ops-detail-grid">
                        <div class="ops-detail-item"><dt>Type</dt><dd>{{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}</dd></div>
                        <div class="ops-detail-item"><dt>Source IP</dt><dd class="ops-technical">{{ $incident->source_ip ?: 'Not recorded' }}</dd></div>
                        <div class="ops-detail-item"><dt>IP policy decision</dt><dd>@if (($ipDecision ?? null) === 'blocked')<span class="ops-badge ops-badge--red">Blocked</span>@elseif (($ipDecision ?? null) === 'allowed')<span class="ops-badge ops-badge--green">Allowed</span>@else No matching rule @endif</dd></div>
                        <div class="ops-detail-item"><dt>Target account</dt><dd>{{ $incident->user?->name ?? 'Not linked' }}</dd></div>
                        <div class="ops-detail-item"><dt>Detection rule</dt><dd>{{ $incident->detection_rule ?: 'Not recorded' }}</dd></div>
                        <div class="ops-detail-item"><dt>Related events</dt><dd>{{ $incident->event_count ?: 0 }}</dd></div>
                        <div class="ops-detail-item"><dt>First detected</dt><dd>{{ $incident->first_detected_at?->format('Y-m-d H:i:s') ?? 'Not recorded' }}</dd></div>
                        <div class="ops-detail-item"><dt>Last detected</dt><dd>{{ $incident->last_detected_at?->format('Y-m-d H:i:s') ?? 'Not recorded' }}</dd></div>
                    </dl>
                    <div class="ops-list-row"><div><p class="ops-list-title">Detection reason</p><p class="ops-list-meta ops-wrap-anywhere">{{ $incident->detection_reason ?: 'No detection reason recorded.' }}</p></div></div>
                    <div class="ops-list-row"><div><p class="ops-list-title">Description</p><p class="ops-list-meta whitespace-pre-wrap ops-wrap-anywhere">{{ $incident->description ?: 'No description provided.' }}</p></div></div>
                </section>

                <section class="ops-panel ops-panel--padded" aria-labelledby="remarks-title">
                    <div class="ops-panel-header"><div><h2 id="remarks-title" class="ops-panel-title">Investigation remarks</h2><p class="ops-panel-description">Analyst observations retained with author and time.</p></div></div>
                    <div class="ops-list">@forelse ($incident->remarks as $remark)<article class="ops-list-row"><div><p class="ops-list-title">{{ $remark->author?->name ?? 'System' }}</p><p class="ops-list-meta whitespace-pre-wrap ops-wrap-anywhere">{{ $remark->remark }}</p></div><time class="ops-cell-meta" datetime="{{ $remark->created_at?->toIso8601String() }}">{{ $remark->created_at?->format('Y-m-d H:i:s') }}</time></article>@empty<x-ui.empty-state title="No investigation remarks" description="Add the first analyst observation below." />@endforelse</div>
                    <form method="POST" action="{{ route('incidents.remarks.store', $incident) }}" class="ops-stack">@csrf<label class="ops-field"><span class="ops-label">Add investigation remark <span class="ops-required">required</span></span><textarea name="remark" rows="4" required></textarea></label><div><button class="ops-button ops-button--primary">Add remark</button></div></form>
                </section>

                <section class="ops-panel ops-panel--padded" aria-labelledby="details-title">
                    <div class="ops-panel-header"><div><h2 id="details-title" class="ops-panel-title">Case details</h2><p class="ops-panel-description">Correct descriptive fields without changing workflow status.</p></div></div>
                    <form method="POST" action="{{ route('incidents.update', $incident) }}" class="ops-stack">@csrf @method('PUT')<div class="ops-form-grid">
                        <label class="ops-field ops-field--wide"><span class="ops-label">Title</span><input name="title" value="{{ $incident->title }}" required></label>
                        <label class="ops-field"><span class="ops-label">Type</span><input name="incident_type" value="{{ $incident->incident_type }}" required></label>
                        <label class="ops-field"><span class="ops-label">Source IP</span><input name="source_ip" value="{{ $incident->source_ip }}"></label>
                        <label class="ops-field ops-field--full"><span class="ops-label">Detection reason</span><input name="detection_reason" value="{{ $incident->detection_reason }}"></label>
                        <label class="ops-field ops-field--full"><span class="ops-label">Description</span><textarea name="description" rows="4">{{ $incident->description }}</textarea></label>
                    </div><div><button class="ops-button ops-button--secondary">Save case details</button></div></form>
                </section>
            </div>

            <aside class="ops-stack" aria-label="Incident workflow controls">
                <section class="ops-panel ops-panel--padded">
                    <div class="ops-panel-header"><div><h2 class="ops-panel-title">Response workflow</h2><p class="ops-panel-description">Ownership, classification, and lifecycle.</p></div></div>
                    <form method="POST" action="{{ route('incidents.status.update', $incident) }}" class="ops-stack">@csrf @method('PATCH')<label class="ops-field"><span class="ops-label">Status</span><select name="status">@foreach (['open','investigating','contained','resolved','closed'] as $status)<option value="{{ $status }}" @selected($incident->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label class="ops-field"><span class="ops-label">Status reason</span><input name="reason" placeholder="Optional reason for the transition"></label><button class="ops-button ops-button--primary">Update status</button></form>
                    <hr class="ops-divider">
                    <form method="POST" action="{{ route('incidents.severity.update', $incident) }}" class="ops-stack">@csrf @method('PATCH')<label class="ops-field"><span class="ops-label">Severity</span><select name="severity">@foreach (['Normal','Warning','Suspicious','High','Critical'] as $level)<option value="{{ $level }}" @selected($incident->severity === $level)>{{ $level }}</option>@endforeach</select></label><button class="ops-button ops-button--secondary">Update severity</button></form>
                    <hr class="ops-divider">
                    <form method="POST" action="{{ route('incidents.assign', $incident) }}" class="ops-stack">@csrf<label class="ops-field"><span class="ops-label">Assigned administrator</span><select name="assigned_to">@foreach ($admins as $admin)<option value="{{ $admin->id }}" @selected($incident->assigned_to == $admin->id)>{{ $admin->name }}</option>@endforeach</select></label><button class="ops-button ops-button--secondary">Save assignment</button></form>
                </section>

                <section class="ops-panel ops-panel--padded">
                    <div class="ops-panel-header"><div><h2 class="ops-panel-title">Response record</h2><p class="ops-panel-description">Document containment and resolution work.</p></div></div>
                    <form method="POST" action="{{ route('incidents.response.store', $incident) }}" class="ops-stack">@csrf<label class="ops-field"><span class="ops-label">Response actions</span><textarea name="response_actions" rows="4">{{ $incident->response_actions }}</textarea></label><label class="ops-field"><span class="ops-label">Resolution notes</span><textarea name="resolution_notes" rows="4">{{ $incident->resolution_notes }}</textarea></label><button class="ops-button ops-button--secondary">Save response record</button></form>
                </section>

                <section class="ops-panel ops-panel--padded ops-danger-zone">
                    <div class="ops-panel-header"><div><h2 class="ops-panel-title">IP response</h2><p class="ops-panel-description">High-impact enforcement action for the recorded source.</p></div></div>
                    @if ($incident->source_ip)
                        <p class="ops-technical ops-wrap-anywhere">{{ $incident->source_ip }}</p>
                        @if (($ipRules ?? collect())->isNotEmpty())<ul class="ops-list">@foreach ($ipRules as $rule)<li class="ops-list-row"><div><p class="ops-list-title">{{ strtoupper($rule->action) }} · {{ $rule->ip_address }}</p><p class="ops-list-meta">{{ ucfirst($rule->source) }} · {{ $rule->is_enabled ? 'Enabled' : 'Disabled' }}</p></div></li>@endforeach</ul>@else<p class="ops-panel-description">No IP policy rule currently matches this address.</p>@endif
                        <form method="POST" action="{{ route('incidents.block-ip', $incident) }}" onsubmit="return confirm('Block {{ $incident->source_ip }}? A permanent BLOCK rule and incident remark will be created.');">@csrf<input type="hidden" name="expiration" value="permanent"><button class="ops-button ops-button--danger">Block source IP</button></form>
                    @else<p class="ops-panel-description">No source IP is recorded, so enforcement is unavailable.</p>@endif
                </section>

                <section class="ops-panel ops-panel--padded"><div class="ops-panel-header"><div><h2 class="ops-panel-title">Case timeline</h2><p class="ops-panel-description">Recorded workflow history.</p></div></div><ol class="ops-list">@forelse ($timeline as $entry)<li class="ops-list-row"><div><p class="ops-list-title">{{ $entry['title'] }}</p><p class="ops-list-meta ops-wrap-anywhere">{{ $entry['detail'] }}</p></div><time class="ops-cell-meta">{{ $entry['timestamp']?->format('Y-m-d H:i:s') ?? 'Not recorded' }}</time></li>@empty<x-ui.empty-state title="No timeline entries" description="Workflow changes will appear here." />@endforelse</ol></section>
            </aside>
        </div>
    </div>
</x-layouts.app>
