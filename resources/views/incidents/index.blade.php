<x-layouts.app title="Incident management - INTSEC" realtime-entities="incident,security_alert">
    <div class="ops-page">
        <x-ui.page-header kicker="Response operations" title="Incident management" description="Investigate, contain, and resolve correlated security activity with a traceable response record.">
            <x-slot:context><span class="ops-context-pill ops-context-pill--live">Live incident queue</span><span class="ops-context-pill">{{ $incidents->count() }} incidents on this page</span></x-slot:context>
            <x-slot:actions><button type="button" class="ops-button ops-button--primary" data-modal-trigger="create-incident-modal">Create incident</button></x-slot:actions>
        </x-ui.page-header>

        @if ($errors->any())
            <div class="ops-banner ops-banner--danger" role="alert"><div><strong>Incident was not saved.</strong><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
        @endif
        @if (session('status') === 'incident-created')<div class="ops-banner ops-banner--success" role="status">Incident created and added to the response queue.</div>@endif

        @php
            $stats = [
                ['label' => 'Open incidents', 'value' => $summary['open'], 'context' => 'Awaiting triage'],
                ['label' => 'Investigating', 'value' => $summary['investigating'], 'context' => 'Active investigations'],
                ['label' => 'High / critical', 'value' => $summary['high_critical'], 'context' => 'Priority response'],
                ['label' => 'Contained', 'value' => $summary['contained'], 'context' => 'Threat constrained'],
                ['label' => 'Resolved', 'value' => $summary['resolved'], 'context' => 'Response completed'],
            ];
        @endphp
        <section class="ops-kpi-grid" aria-label="Incident queue summary">@foreach ($stats as $stat)<x-security.metric-card :label="$stat['label']" :value="$stat['value']" :context="$stat['context']" />@endforeach</section>

        <form method="GET" action="{{ route('incidents.index') }}" class="ops-filter" aria-label="Filter incidents">
            <div class="ops-filter-heading"><div><h2 class="ops-filter-title">Incident filters</h2><p class="ops-filter-state">Narrow the response queue without changing incident state.</p></div></div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search"><span class="ops-label">Search</span><input name="search" value="{{ request('search') }}" placeholder="Incident ID, title, IP, or reason"></label>
                <label class="ops-field"><span class="ops-label">Type</span><select name="incident_type"><option value="">All types</option>@foreach (['authentication' => 'Authentication','authorization' => 'Authorization','ip_activity' => 'IP activity'] as $value => $label)<option value="{{ $value }}" @selected(request('incident_type') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="ops-field"><span class="ops-label">Source</span><select name="source"><option value="">All sources</option>@foreach(config('intsec.sources') as $value => $label)<option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="ops-field"><span class="ops-label">Severity</span><select name="severity"><option value="">All severities</option>@foreach (['Normal','Warning','Suspicious','High','Critical'] as $level)<option value="{{ $level }}" @selected(request('severity') === $level)>{{ $level }}</option>@endforeach</select></label>
                <label class="ops-field"><span class="ops-label">Status</span><select name="status"><option value="">All statuses</option>@foreach (['open','investigating','contained','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label class="ops-field"><span class="ops-label">From date</span><input type="date" name="from_date" value="{{ request('from_date') }}"></label>
                <label class="ops-field"><span class="ops-label">To date</span><input type="date" name="to_date" value="{{ request('to_date') }}"></label>
                <div class="ops-filter-actions"><a href="{{ route('incidents.index') }}" class="ops-button ops-button--quiet">Clear</a><button class="ops-button ops-button--secondary">Apply filters</button></div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="incident-table-title">
            <div class="ops-panel-header"><div><h2 id="incident-table-title" class="ops-panel-title">Incident queue</h2><p class="ops-panel-description">Prioritized case records and ownership.</p></div></div>
            <div class="ops-table-scroll"><table class="ops-table ops-table--xwide"><caption>Filtered security incident queue</caption>
                <thead><tr><th>Incident</th><th>Summary</th><th>Source</th><th>Severity</th><th>Status</th><th>Source IP</th><th>Owner</th><th>Activity</th><th><span class="sr-only">Action</span></th></tr></thead>
                <tbody>@forelse ($incidents as $incident)<tr>
                    <td><span class="ops-cell-primary ops-technical">{{ $incident->incident_id }}</span><span class="ops-cell-meta">{{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}</span></td>
                    <td><a class="ops-panel-link ops-wrap-anywhere" href="{{ route('incidents.show', $incident) }}">{{ $incident->title }}</a><span class="ops-cell-meta">{{ \Illuminate\Support\Str::limit($incident->detection_reason ?: 'No detection reason recorded', 80) }}</span></td>
                    <td><x-security.source-badge :source="$incident->source" /></td><td><x-security.severity-badge :severity="$incident->severity" /></td><td><x-security.status-badge :status="$incident->status" /></td>
                    <td><span class="ops-technical ops-wrap-anywhere" title="{{ $incident->source_ip }}">{{ $incident->source_ip ?: 'Not recorded' }}</span></td><td>{{ $incident->assignedAdministrator?->name ?? 'Unassigned' }}</td>
                    <td><span class="ops-cell-primary">{{ $incident->last_detected_at?->diffForHumans() ?? $incident->updated_at?->diffForHumans() }}</span><span class="ops-cell-meta">{{ $incident->event_count ?? 0 }} related events</span></td>
                    <td><a class="ops-button ops-button--quiet" href="{{ route('incidents.show', $incident) }}">Investigate</a></td>
                </tr>@empty<tr><td colspan="9"><x-ui.empty-state title="No incidents found" description="No incident records match the current filters." /></td></tr>@endforelse</tbody>
            </table></div><x-ui.simple-pagination :paginator="$incidents" noun="incidents" />
        </section>
    </div>

    <div id="create-incident-modal" class="ops-modal" data-modal data-modal-auto-open="{{ $errors->any() ? 'true' : 'false' }}" role="dialog" aria-modal="true" aria-labelledby="create-incident-title" aria-hidden="true">
        <div class="ops-modal-dialog"><div class="ops-modal-header"><div><p class="ops-kicker">Response operations</p><h2 id="create-incident-title" class="ops-panel-title">Create incident</h2></div><button type="button" class="ops-button ops-button--quiet" data-modal-close>Close</button></div>
            <form method="POST" action="{{ route('incidents.store') }}" class="ops-modal-body ops-stack">@csrf<input type="hidden" name="status" value="open"><div class="ops-form-grid">
                <label class="ops-field ops-field--wide"><span class="ops-label">Title <span class="ops-required">required</span></span><input name="title" value="{{ old('title') }}" required></label>
                <label class="ops-field"><span class="ops-label">Type <span class="ops-required">required</span></span><input name="incident_type" value="{{ old('incident_type', 'authentication') }}" required></label>
                <label class="ops-field"><span class="ops-label">Severity</span><select name="severity">@foreach (['Normal','Warning','Suspicious','High','Critical'] as $level)<option value="{{ $level }}" @selected(old('severity') === $level)>{{ $level }}</option>@endforeach</select></label>
                <label class="ops-field"><span class="ops-label">Source IP</span><input name="source_ip" value="{{ old('source_ip') }}" placeholder="IPv4 or IPv6 address"></label>
                <label class="ops-field ops-field--wide"><span class="ops-label">Detection reason</span><input name="detection_reason" value="{{ old('detection_reason') }}"></label>
                <label class="ops-field"><span class="ops-label">Target account</span><select name="user_id"><option value="">None</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>
                <label class="ops-field ops-field--full"><span class="ops-label">Description</span><textarea name="description" rows="4">{{ old('description') }}</textarea></label>
            </div><div class="ops-filter-actions"><button type="button" class="ops-button ops-button--quiet" data-modal-close>Cancel</button><button class="ops-button ops-button--primary">Create incident</button></div></form>
        </div>
    </div>
</x-layouts.app>
