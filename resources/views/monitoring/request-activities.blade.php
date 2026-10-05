<x-layouts.app :title="$monitoringSource->label().' Request Activity - INTSEC'" wide realtime-entities="request_activity" :realtime-source="$monitoringSource->value">
    @php
        $hasFilters = collect(request()->only(['search', 'ip', 'method', 'status_code']))
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' monitoring'"
            title="Request activity"
            description="Application-visible HTTP metadata only. Request bodies, cookies, credentials, and authorization headers are not collected."
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live request telemetry</span>
                <span class="ops-context-pill">Source <span class="ops-technical">{{ $monitoringSource->value }}</span></span>
            </x-slot:context>
            <x-slot:actions>
                <a class="ops-button ops-button--secondary" href="{{ route('monitoring.overview', $monitoringSource->value) }}">Back to overview</a>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="GET" class="ops-filter" aria-label="Request activity filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter request telemetry</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="request-search">
                    <span class="ops-label">Search</span>
                    <input id="request-search" name="search" value="{{ request('search') }}" placeholder="Path, route, request ID, or IP">
                </label>
                <label class="ops-field" for="request-ip">
                    <span class="ops-label">IP address</span>
                    <input id="request-ip" name="ip" value="{{ request('ip') }}" placeholder="IPv4 or IPv6">
                </label>
                <label class="ops-field" for="request-method">
                    <span class="ops-label">HTTP method</span>
                    <select id="request-method" name="method">
                        <option value="">All methods</option>
                        @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method)
                            <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="request-status">
                    <span class="ops-label">Status code</span>
                    <input id="request-status" type="number" min="100" max="599" name="status_code" value="{{ request('status_code') }}" placeholder="e.g. 404">
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ route('monitoring.request-activities', $monitoringSource->value) }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="request-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="request-results-title" class="ops-panel-title">Request records</h2>
                    <p class="ops-panel-description">Newest persisted observations first. Times are shown in {{ config('app.timezone') }}.</p>
                </div>
                <span class="ops-context-pill">{{ $activities->count() }} on this page</span>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable request activity table">
                <table class="ops-table ops-table--wide">
                    <caption class="sr-only">Request activity for {{ $monitoringSource->label() }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Occurred</th>
                            <th scope="col">Method</th>
                            <th scope="col">Endpoint</th>
                            <th scope="col">Status</th>
                            <th scope="col">Source IP</th>
                            <th scope="col">Duration</th>
                            <th scope="col">Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $activity)
                            @php
                                $statusFamily = match (true) {
                                    $activity->status_code >= 500 => 'server',
                                    $activity->status_code >= 400 => 'client',
                                    $activity->status_code >= 300 => 'redirect',
                                    default => 'ok',
                                };
                                $classificationTone = match ($activity->classification) {
                                    'malicious' => 'red',
                                    'suspicious' => 'amber',
                                    default => 'green',
                                };
                            @endphp
                            <tr>
                                <td class="ops-numeric">{{ $activity->occurred_at?->format('M j, Y H:i:s') ?? '—' }}</td>
                                <td><span class="ops-badge ops-badge--cyan">{{ $activity->method }}</span></td>
                                <td>
                                    <span class="ops-table-value ops-technical" title="{{ $activity->path }}">{{ $activity->path }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere">{{ $activity->route_name ?: 'Unnamed route' }} · {{ $activity->request_id ?: 'No request ID' }}</span>
                                </td>
                                <td><span class="ops-http ops-http--{{ $statusFamily }}">{{ $activity->status_code }}</span></td>
                                <td><span class="ops-technical ops-wrap-anywhere" title="{{ $activity->ip_address }}">{{ $activity->ip_address ?? 'Unknown' }}</span></td>
                                <td class="ops-numeric">{{ $activity->duration_ms === null ? '—' : number_format($activity->duration_ms).' ms' }}</td>
                                <td><span class="ops-badge ops-badge--{{ $classificationTone }}">{{ ucfirst($activity->classification ?? 'normal') }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No requests match these filters' : 'No request telemetry yet'"
                                        :description="$hasFilters ? 'Clear or adjust the current filters to broaden the results.' : 'Request observations will appear after this source begins reporting activity.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$activities" label="requests" />
        </section>
    </div>
</x-layouts.app>
