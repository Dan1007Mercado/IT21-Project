<x-layouts.app title="IP Request Frequency - INTSEC" wide realtime-entities="request_activity,security_alert" :realtime-source="$monitoringSource->value">
    @php
        $hasFilters = collect(request()->only(['search', 'ip', 'range', 'classification', 'status_family', 'min_requests']))
            ->except('range')->filter(fn ($value) => filled($value))->isNotEmpty() || request('range', '7d') !== '7d';
        $clearUrl = auth()->user()->isAdministrator()
            ? route('monitoring.attack-frequency', $monitoringSource->value)
            : route('attack-frequency');
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' request telemetry'"
            title="IP request frequency"
            :description="'Read-only aggregation of '.$monitoringSource->label().' application requests. Viewing this page never runs detection or creates alerts.'"
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live request updates</span>
                <span class="ops-context-pill">Window {{ ['1h' => '1 hour', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'][request('range', '7d')] ?? '7 days' }}</span>
            </x-slot:context>
        </x-ui.page-header>

        <form method="GET" class="ops-filter" aria-label="IP request frequency filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter frequency analysis</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="frequency-search">
                    <span class="ops-label">Search IP</span>
                    <input id="frequency-search" name="search" value="{{ request('search') }}" placeholder="Exact or partial IPv4 / IPv6">
                </label>
                <label class="ops-field" for="frequency-ip">
                    <span class="ops-label">IP contains</span>
                    <input id="frequency-ip" name="ip" value="{{ request('ip') }}" placeholder="Additional IP filter">
                </label>
                <label class="ops-field" for="frequency-range">
                    <span class="ops-label">Time window</span>
                    <select id="frequency-range" name="range">
                        @foreach (['1h' => 'Last hour', '24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('range', '7d') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="frequency-classification">
                    <span class="ops-label">Classification</span>
                    <select id="frequency-classification" name="classification">
                        <option value="">All classifications</option>
                        @foreach (['normal', 'suspicious', 'malicious'] as $value)
                            <option value="{{ $value }}" @selected(request('classification') === $value)>{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="frequency-status-family">
                    <span class="ops-label">HTTP status family</span>
                    <select id="frequency-status-family" name="status_family">
                        <option value="">All status families</option>
                        @foreach (['2xx', '3xx', '4xx', '5xx'] as $value)
                            <option value="{{ $value }}" @selected(request('status_family') === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="frequency-minimum">
                    <span class="ops-label">Minimum requests</span>
                    <input id="frequency-minimum" type="number" min="1" name="min_requests" value="{{ request('min_requests') }}" placeholder="Any count">
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ $clearUrl }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="frequency-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="frequency-results-title" class="ops-panel-title">Request concentration by IP</h2>
                    <p class="ops-panel-description">Counts and HTTP-error context help analysts scan frequency without treating volume alone as malicious.</p>
                </div>
                <span class="ops-context-pill">{{ $attackFrequency->count() }} on this page</span>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable IP request frequency table">
                <table class="ops-table ops-table--wide">
                    <caption class="sr-only">Request frequency grouped by source IP</caption>
                    <thead>
                        <tr>
                            <th scope="col">Source IP</th>
                            <th scope="col">Requests</th>
                            <th scope="col">Last seen</th>
                            <th scope="col">Top path</th>
                            <th scope="col">4xx / 403 / 404</th>
                            <th scope="col">Classification</th>
                            <th scope="col">Policy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attackFrequency as $row)
                            @php
                                $classificationTone = match ($row['classification']) {
                                    'malicious' => 'red',
                                    'suspicious' => 'amber',
                                    default => 'green',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="ops-technical ops-wrap-anywhere" title="{{ $row['ip'] }}">{{ $row['ip'] }}</span>
                                    <span class="ops-cell-meta">{{ ucfirst($row['ip_type']) }} address</span>
                                </td>
                                <td class="ops-cell-primary ops-numeric">{{ number_format($row['count']) }}</td>
                                <td class="ops-numeric" title="{{ \Carbon\Carbon::parse($row['last_seen'])->format('M j, Y H:i:s T') }}">{{ \Carbon\Carbon::parse($row['last_seen'])->diffForHumans() }}</td>
                                <td><span class="ops-table-value ops-technical" title="{{ $row['route'] }}">{{ $row['route'] ?? '—' }}</span></td>
                                <td>
                                    <span class="ops-numeric">{{ number_format($row['client_errors']) }} / {{ number_format($row['forbidden_count']) }} / {{ number_format($row['not_found_count']) }}</span>
                                    <span class="ops-cell-meta">Client errors / forbidden / not found</span>
                                </td>
                                <td><span class="ops-badge ops-badge--{{ $classificationTone }}">{{ ucfirst($row['classification'] ?? 'normal') }}</span></td>
                                <td><span class="ops-badge ops-badge--{{ $row['is_blocked'] ? 'red' : 'muted' }}">{{ $row['is_blocked'] ? 'Blocked' : 'No block' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No IP activity matches these filters' : 'No request frequency data yet'"
                                        :description="$hasFilters ? 'Clear or adjust the active filters to broaden the results.' : 'Grouped source-IP activity will appear after request telemetry is recorded.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$attackFrequency" label="IP groups" />
        </section>
    </div>
</x-layouts.app>
