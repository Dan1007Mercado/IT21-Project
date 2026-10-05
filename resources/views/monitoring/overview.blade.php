<x-layouts.app :title="$monitoringSource->label().' Monitoring - INTSEC'" wide realtime-entities="*" :realtime-source="$monitoringSource->value">
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' monitoring'"
            :title="$monitoringSource->label().' monitoring overview'"
            description="A source-isolated operational view of application requests, authentication activity, detections, alerts, and incidents."
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live updates enabled</span>
                <span class="ops-context-pill">Source <span class="ops-technical">{{ $monitoringSource->value }}</span></span>
                <span class="ops-context-pill">{{ $periodLabel }}</span>
            </x-slot:context>
            <x-slot:actions>
                <a class="ops-button ops-button--secondary" href="{{ route('monitoring.request-activities', $monitoringSource->value) }}">View requests</a>
                @if ($monitoringSource === \App\Enums\MonitoringSource::HotelBooking)
                    <a class="ops-button ops-button--secondary" href="{{ route('monitoring.security-events', $monitoringSource->value) }}">View security events</a>
                @else
                    <a class="ops-button ops-button--secondary" href="{{ route('admin.audit-logs') }}">View audit logs</a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <section class="ops-section" aria-labelledby="overview-kpis">
            <h2 id="overview-kpis" class="sr-only">Operational metrics for the last 24 hours</h2>
            <div class="ops-kpi-grid">
                @foreach ($kpis as $metric)
                    <x-security.metric-card
                        :label="$metric['label']"
                        :value="number_format($metric['value'])"
                        :tone="$metric['tone']"
                        :context="$metric['context']"
                        :trend="$metric['trend']"
                    >
                        @if (array_key_exists('rate', $metric) && $metric['rate'] !== null)
                            <p class="ops-metric-trend" data-direction="{{ $metric['rate'] >= 25 ? 'up' : 'flat' }}">
                                {{ number_format($metric['rate'], 1) }}% failure rate
                            </p>
                        @endif
                    </x-security.metric-card>
                @endforeach
            </div>
        </section>

        @php
            $requestTotal = collect($requestTrend)->sum('count');
            $authTotal = array_sum($authenticationBreakdown);
            $eventTotal = array_sum($eventSeverityDistribution);
        @endphp

        <section class="ops-section ops-grid-3" aria-label="Monitoring trends and distributions">
            <article class="ops-panel lg:col-span-2">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">Request volume by hour</h2>
                        <p class="ops-panel-description">Application requests observed during the rolling 24-hour window.</p>
                    </div>
                    <span class="ops-context-pill">{{ number_format($requestTotal) }} total</span>
                </div>
                @if ($requestTotal > 0)
                    <div class="ops-chart">
                        <canvas id="overview-request-trend" role="img" aria-label="Hourly request counts for {{ $monitoringSource->label() }} over the last 24 hours"></canvas>
                    </div>
                @else
                    <x-ui.empty-state title="No request telemetry yet" description="The hourly request chart will appear after this source submits request activity." />
                @endif
            </article>

            <div class="ops-stack">
                <article class="ops-panel">
                    <div class="ops-panel-header">
                        <div>
                            <h2 class="ops-panel-title">Authentication outcomes</h2>
                            <p class="ops-panel-description">Login attempts in the last 24 hours.</p>
                        </div>
                    </div>
                    @if ($authTotal > 0)
                        <ul class="ops-list">
                            <li class="ops-list-row">
                                <span><span class="ops-list-title">Successful</span><span class="ops-list-meta">Verified login completion</span></span>
                                <span class="ops-badge ops-badge--green">{{ number_format($authenticationBreakdown['successful']) }}</span>
                            </li>
                            <li class="ops-list-row">
                                <span><span class="ops-list-title">Failed</span><span class="ops-list-meta">Rejected login attempt</span></span>
                                <span class="ops-badge ops-badge--red">{{ number_format($authenticationBreakdown['failed']) }}</span>
                            </li>
                        </ul>
                    @else
                        <x-ui.empty-state title="No authentication attempts" description="No login attempts were recorded for this source in the last 24 hours." />
                    @endif
                </article>

                <article class="ops-panel">
                    <div class="ops-panel-header">
                        <div>
                            <h2 class="ops-panel-title">Event severity</h2>
                            <p class="ops-panel-description">Security events interpreted in the last 24 hours.</p>
                        </div>
                    </div>
                    @if ($eventTotal > 0)
                        <ul class="ops-list">
                            @foreach (['Critical', 'High', 'Suspicious', 'Warning', 'Normal', 'Medium', 'Low'] as $severity)
                                @if (($eventSeverityDistribution[$severity] ?? 0) > 0)
                                    <li class="ops-list-row">
                                        <x-security.severity-badge :severity="$severity" />
                                        <span class="ops-numeric">{{ number_format($eventSeverityDistribution[$severity]) }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <x-ui.empty-state title="No security events recorded" description="No interpreted security events were created for this source in the last 24 hours." />
                    @endif
                </article>
            </div>
        </section>

        <section class="ops-section ops-grid-2" aria-label="Recent source activity">
            <article class="ops-panel">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">Recent requests</h2>
                        <p class="ops-panel-description">Latest persisted request telemetry for this source.</p>
                    </div>
                    <a class="ops-panel-link" href="{{ route('monitoring.request-activities', $monitoringSource->value) }}">View all →</a>
                </div>
                @if ($recentRequests->isNotEmpty())
                    <ul class="ops-list">
                        @foreach ($recentRequests as $activity)
                            @php
                                $statusFamily = match (true) {
                                    $activity->status_code >= 500 => 'server',
                                    $activity->status_code >= 400 => 'client',
                                    $activity->status_code >= 300 => 'redirect',
                                    default => 'ok',
                                };
                            @endphp
                            <li class="ops-list-row">
                                <span>
                                    <span class="ops-list-title ops-technical ops-wrap-anywhere" title="{{ $activity->method }} {{ $activity->path }}">{{ $activity->method }} {{ $activity->path }}</span>
                                    <span class="ops-list-meta">{{ $activity->occurred_at?->format('M j, Y H:i:s T') }} · {{ $activity->ip_address ?? 'Unknown IP' }}</span>
                                </span>
                                <span class="ops-http ops-http--{{ $statusFamily }}">{{ $activity->status_code }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="No request telemetry yet" description="No {{ $monitoringSource->label() }} request activity has been persisted." />
                @endif
            </article>

            <article class="ops-panel">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">Recent security events</h2>
                        <p class="ops-panel-description">Latest interpreted activity for this source.</p>
                    </div>
                    @if ($monitoringSource === \App\Enums\MonitoringSource::HotelBooking)
                        <a class="ops-panel-link" href="{{ route('monitoring.security-events', $monitoringSource->value) }}">View all →</a>
                    @endif
                </div>
                @if ($recentEvents->isNotEmpty())
                    <ul class="ops-list">
                        @foreach ($recentEvents as $event)
                            <li class="ops-list-row">
                                <span>
                                    <span class="ops-list-title ops-wrap-anywhere" title="{{ $event->title }}">{{ $event->title }}</span>
                                    <span class="ops-list-meta">{{ str($event->event_type)->replace('_', ' ')->title() }} · {{ $event->occurred_at?->format('M j, Y H:i:s T') }}</span>
                                </span>
                                <x-security.severity-badge :severity="$event->severity" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="No security events recorded" description="No security-relevant activity has been interpreted for this source." />
                @endif
            </article>
        </section>
    </div>

    @if ($requestTotal > 0)
        <x-slot:scripts>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                new Chart(document.getElementById('overview-request-trend'), {
                    type: 'line',
                    data: {
                        labels: @json(collect($requestTrend)->pluck('label')),
                        datasets: [{
                            label: 'Requests',
                            data: @json(collect($requestTrend)->pluck('count')),
                            borderColor: '#38bdf8',
                            backgroundColor: 'rgba(56, 189, 248, .1)',
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                            fill: true,
                            tension: .32,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: { displayColors: false },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#8eabc0', maxTicksLimit: 8, maxRotation: 0 },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(56, 189, 248, .08)' },
                                ticks: { color: '#8eabc0', precision: 0 },
                            },
                        },
                    },
                });
            </script>
        </x-slot:scripts>
    @endif
</x-layouts.app>
