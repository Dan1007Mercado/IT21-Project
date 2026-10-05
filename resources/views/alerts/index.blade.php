<x-layouts.app title="Security Alerts - INTSEC" wide realtime-entities="security_alert">
    @php
        $hasFilters = collect(request()->only(['search', 'source', 'severity', 'status', 'alert_type', 'assigned_to', 'source_ip', 'range', 'from_date', 'to_date']))
            ->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            kicker="Security operations"
            title="Security alerts"
            description="Actionable, explainable detections requiring analyst attention. Prioritize by severity, workflow state, ownership, and incident relationship."
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live alert updates</span>
                <span class="ops-context-pill">{{ number_format($summary['new']) }} new</span>
                <span class="ops-context-pill">{{ number_format($summary['investigating']) }} investigating</span>
            </x-slot:context>
            <x-slot:actions>
                <a class="ops-button ops-button--secondary" href="{{ route('incidents.index') }}">View incidents</a>
                <a class="ops-button ops-button--secondary" href="{{ route('alerts.export', request()->query()) }}">Export CSV</a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="ops-section" aria-label="Alert queue metrics">
            <div class="ops-kpi-grid">
                <x-security.metric-card label="New" :value="number_format($summary['new'])" context="Not yet acknowledged" />
                <x-security.metric-card label="Critical" :value="number_format($summary['critical'])" tone="red" context="All critical-severity alerts" />
                <x-security.metric-card label="High" :value="number_format($summary['high'])" tone="amber" context="All high-severity alerts" />
                <x-security.metric-card label="Acknowledged" :value="number_format($summary['acknowledged'])" tone="zinc" context="Accepted for analyst review" />
                <x-security.metric-card label="Investigating" :value="number_format($summary['investigating'])" tone="amber" context="Active investigations" />
                <x-security.metric-card label="Resolved" :value="number_format($summary['resolved'])" tone="emerald" context="Completed alert workflows" />
            </div>
        </section>

        @if (session('status'))
            <div class="ops-banner ops-banner--success" role="status">Alert workflow updated successfully.</div>
        @endif
        @if ($errors->any())
            <div class="ops-banner ops-banner--danger" role="alert">
                <strong>The alert action could not be completed.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="GET" class="ops-filter" aria-label="Security alert filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter alert queue</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="alert-search">
                    <span class="ops-label">Search</span>
                    <input id="alert-search" name="search" value="{{ request('search') }}" placeholder="Alert ID, title, detection type, or IP">
                </label>
                <label class="ops-field" for="alert-severity">
                    <span class="ops-label">Severity</span>
                    <select id="alert-severity" name="severity">
                        <option value="">All severities</option>
                        @foreach (['Normal', 'Warning', 'Suspicious', 'High', 'Critical'] as $severity)
                            <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ $severity }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="alert-status">
                    <span class="ops-label">Workflow status</span>
                    <select id="alert-status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['new', 'acknowledged', 'investigating', 'resolved', 'dismissed'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="alert-source">
                    <span class="ops-label">Source</span>
                    <select id="alert-source" name="source">
                        <option value="">All sources</option>
                        @foreach (config('intsec.sources') as $value => $label)
                            <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="alert-source-ip">
                    <span class="ops-label">Source IP</span>
                    <input id="alert-source-ip" name="source_ip" value="{{ request('source_ip') }}" placeholder="IPv4 or IPv6">
                </label>
                <label class="ops-field" for="alert-type">
                    <span class="ops-label">Detection type</span>
                    <input id="alert-type" name="alert_type" value="{{ request('alert_type') }}" placeholder="Rule or detection type">
                </label>
                <label class="ops-field" for="alert-assignee">
                    <span class="ops-label">Assignee</span>
                    <select id="alert-assignee" name="assigned_to">
                        <option value="">All assignees</option>
                        @foreach ($admins as $administrator)
                            <option value="{{ $administrator->id }}" @selected((string) request('assigned_to') === (string) $administrator->id)>{{ $administrator->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="alert-range">
                    <span class="ops-label">Quick date range</span>
                    <select id="alert-range" name="range">
                        <option value="">Any time</option>
                        <option value="today" @selected(request('range') === 'today')>Today</option>
                        <option value="7d" @selected(request('range') === '7d')>Last 7 days</option>
                        <option value="30d" @selected(request('range') === '30d')>Last 30 days</option>
                    </select>
                </label>
                <label class="ops-field" for="alert-from">
                    <span class="ops-label">From date</span>
                    <input id="alert-from" type="date" name="from_date" value="{{ request('from_date') }}">
                </label>
                <label class="ops-field" for="alert-to">
                    <span class="ops-label">To date</span>
                    <input id="alert-to" type="date" name="to_date" value="{{ request('to_date') }}">
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ route('alerts.index') }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="alert-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="alert-results-title" class="ops-panel-title">Alert queue</h2>
                    <p class="ops-panel-description">Select alerts for a controlled bulk acknowledgement or dismissal.</p>
                </div>
                <form id="bulk-alerts" method="POST" action="{{ route('alerts.bulk') }}" class="ops-action-group" data-requires-selection="alert_ids[]">
                    @csrf
                    <label class="sr-only" for="bulk-action">Bulk action</label>
                    <select id="bulk-action" name="bulk_action" class="ops-control">
                        <option value="acknowledge">Acknowledge selected</option>
                        <option value="dismiss">Dismiss selected</option>
                    </select>
                    <button type="submit" class="ops-button ops-button--secondary">Apply to selected</button>
                </form>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable security alert table">
                <table class="ops-table ops-table--xwide">
                    <caption class="sr-only">Actionable security alerts</caption>
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Select</span><input type="checkbox" data-select-all=".alert-select" aria-label="Select all alerts on this page"></th>
                            <th scope="col">Alert / detection</th>
                            <th scope="col">Source</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Status</th>
                            <th scope="col">Source IP / target</th>
                            <th scope="col">Assignee</th>
                            <th scope="col">Incident</th>
                            <th scope="col">Occurred</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            <tr>
                                <td><input form="bulk-alerts" class="alert-select" type="checkbox" name="alert_ids[]" value="{{ $alert->id }}" aria-label="Select alert {{ $alert->alert_id }}"></td>
                                <td>
                                    <a class="ops-cell-primary ops-wrap-anywhere" href="{{ route('alerts.show', $alert) }}" title="{{ $alert->title }}">{{ $alert->title }}</a>
                                    <span class="ops-cell-meta ops-technical">{{ $alert->alert_id }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere">{{ $alert->typeLabel() }}</span>
                                </td>
                                <td><x-security.source-badge :source="$alert->source" /></td>
                                <td><x-security.severity-badge :severity="$alert->severity" /></td>
                                <td><x-security.status-badge :status="$alert->status" /></td>
                                <td>
                                    <span class="ops-technical ops-wrap-anywhere">{{ $alert->source_ip ?? 'No source IP' }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere">{{ $alert->securityEvent?->user?->name ?? 'No target user' }}</span>
                                </td>
                                <td>{{ $alert->assignedAdministrator?->name ?? 'Unassigned' }}</td>
                                <td>
                                    @if ($alert->incident)
                                        <a class="ops-technical ops-panel-link" href="{{ route('incidents.show', $alert->incident) }}">{{ $alert->incident->incident_id }}</a>
                                    @else
                                        <span class="ops-cell-meta">Not attached</span>
                                    @endif
                                </td>
                                <td class="ops-numeric">{{ $alert->occurred_at?->format('M j, Y H:i') ?? '—' }}</td>
                                <td><a class="ops-button ops-button--quiet" href="{{ route('alerts.show', $alert) }}" aria-label="Open alert {{ $alert->alert_id }}">Open →</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No alerts match these filters' : 'No alerts require attention'"
                                        :description="$hasFilters ? 'Clear or adjust the current filters to broaden the queue.' : 'Actionable detections will appear here when a rule creates an alert.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$alerts" label="alerts" />
        </section>
    </div>
</x-layouts.app>
