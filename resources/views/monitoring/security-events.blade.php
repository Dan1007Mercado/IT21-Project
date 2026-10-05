<x-layouts.app :title="$monitoringSource->label().' Security Events - INTSEC'" wide realtime-entities="security_event" :realtime-source="$monitoringSource->value">
    @php
        $hasFilters = collect(request()->only(['search', 'event_type', 'severity', 'status']))
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' monitoring'"
            title="Security events"
            description="Persisted, interpreted security-relevant activity for this source. Events remain distinct from actionable alerts and managed incidents."
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live event updates</span>
                <span class="ops-context-pill">Source <span class="ops-technical">{{ $monitoringSource->value }}</span></span>
            </x-slot:context>
            <x-slot:actions>
                <a class="ops-button ops-button--secondary" href="{{ route('monitoring.overview', $monitoringSource->value) }}">Back to overview</a>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="GET" class="ops-filter" aria-label="Security event filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter security events</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="event-search">
                    <span class="ops-label">Search</span>
                    <input id="event-search" name="search" value="{{ request('search') }}" placeholder="Title, IP, type, request ID, or external ID">
                </label>
                <label class="ops-field" for="event-type">
                    <span class="ops-label">Event type</span>
                    <input id="event-type" name="event_type" value="{{ request('event_type') }}" placeholder="e.g. login_failed">
                </label>
                <label class="ops-field" for="event-severity">
                    <span class="ops-label">Severity</span>
                    <select id="event-severity" name="severity">
                        <option value="">All severities</option>
                        @foreach (['Normal', 'Warning', 'Suspicious', 'High', 'Critical'] as $severity)
                            <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ $severity }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="event-status">
                    <span class="ops-label">Status</span>
                    <select id="event-status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['new', 'processed', 'acknowledged', 'investigating', 'resolved'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ route('monitoring.security-events', $monitoringSource->value) }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="event-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="event-results-title" class="ops-panel-title">Security event records</h2>
                    <p class="ops-panel-description">Newest interpreted events first. Source-provided severity is normalized before persistence.</p>
                </div>
                <span class="ops-context-pill">{{ $events->count() }} on this page</span>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable security events table">
                <table class="ops-table ops-table--wide">
                    <caption class="sr-only">Security events for {{ $monitoringSource->label() }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Occurred</th>
                            <th scope="col">Event</th>
                            <th scope="col">Type</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Source IP</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td class="ops-numeric">{{ $event->occurred_at?->format('M j, Y H:i:s') ?? '—' }}</td>
                                <td>
                                    <span class="ops-cell-primary ops-wrap-anywhere" title="{{ $event->title }}">{{ $event->title }}</span>
                                    @if ($event->description && $event->description !== $event->title)
                                        <span class="ops-cell-meta ops-table-value" title="{{ $event->description }}">{{ $event->description }}</span>
                                    @endif
                                </td>
                                <td><span class="ops-technical ops-wrap-anywhere">{{ $event->event_type }}</span></td>
                                <td><x-security.severity-badge :severity="$event->severity" /></td>
                                <td><span class="ops-technical ops-wrap-anywhere">{{ $event->source_ip ?? 'Unknown' }}</span></td>
                                <td><x-security.status-badge :status="$event->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No events match these filters' : 'No security events recorded'"
                                        :description="$hasFilters ? 'Clear or adjust the active filters to broaden the results.' : 'Interpreted activity will appear here when security-relevant telemetry is persisted.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$events" label="events" />
        </section>
    </div>
</x-layouts.app>
