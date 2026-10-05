<x-layouts.app title="Application Request Spikes - INTSEC" wide realtime-entities="request_activity,security_alert" :realtime-source="$monitoringSource->value">
    @php
        $requestTotal = $hourlyTrend->sum('count');
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' monitoring'"
            title="Application request spikes"
            :description="'Request-volume monitoring from '.$monitoringSource->label().' HTTP telemetry after TLS termination. This identifies application-level spikes; it is not network-layer DDoS detection.'"
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live request updates</span>
                <span class="ops-context-pill">Rolling 24-hour window</span>
                <span class="ops-context-pill">Threshold {{ number_format($spikeThreshold) }} requests/hour</span>
            </x-slot:context>
        </x-ui.page-header>

        <section class="ops-section" aria-label="Request spike metrics">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-security.metric-card label="Current hour" :value="number_format($currentRequests)" context="Requests in the current hourly bucket" />
                <x-security.metric-card label="24-hour peak" :value="number_format($peakRequests)" tone="amber" context="Highest hourly request count in this window" />
                <x-security.metric-card label="Spike threshold" :value="number_format($spikeThreshold)" tone="zinc" context="Configured requests-per-hour threshold" />
                <x-security.metric-card label="Threshold crossings" :value="number_format($suspiciousSpikes)" :tone="$suspiciousSpikes > 0 ? 'red' : 'emerald'" context="Hourly buckets at or above the threshold" />
            </div>
        </section>

        <section class="ops-section ops-panel" aria-labelledby="request-volume-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="request-volume-title" class="ops-panel-title">Requests by hour</h2>
                    <p class="ops-panel-description">Use the configured threshold as a review signal, not automatic proof of an attack.</p>
                </div>
                <span class="ops-context-pill">{{ number_format($requestTotal) }} requests</span>
            </div>
            @if ($requestTotal > 0)
                <div class="ops-chart">
                    <canvas id="request-volume" role="img" aria-label="Hourly application request volume over the last 24 hours"></canvas>
                </div>
                <div class="ops-pagination" aria-label="Request status context">
                    <span>HTTP client errors (4xx): <strong>{{ number_format($clientErrorCount) }}</strong></span>
                    <span>HTTP server errors (5xx): <strong>{{ number_format($serverErrorCount) }}</strong></span>
                </div>
            @else
                <x-ui.empty-state title="No request telemetry in this window" description="The request trend will appear after application requests are observed for this source." />
            @endif
        </section>

        <section class="ops-section ops-grid-3" aria-label="Request spike supporting context">
            <article class="ops-panel">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">Top source IPs</h2>
                        <p class="ops-panel-description">Highest request counts during the last 24 hours.</p>
                    </div>
                </div>
                @if ($topIps->isNotEmpty())
                    <ul class="ops-list">
                        @foreach ($topIps as $row)
                            <li class="ops-list-row">
                                <span class="ops-list-title ops-technical ops-wrap-anywhere" title="{{ $row->ip_address }}">{{ $row->ip_address }}</span>
                                <strong class="ops-numeric">{{ number_format($row->total) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="No source IP activity" description="No source-address request counts are available for this window." />
                @endif
            </article>

            <article class="ops-panel">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">Top routes and paths</h2>
                        <p class="ops-panel-description">Most frequently requested application endpoints.</p>
                    </div>
                </div>
                @if ($topRoutes->isNotEmpty())
                    <ul class="ops-list">
                        @foreach ($topRoutes as $row)
                            <li class="ops-list-row">
                                <span class="ops-list-title ops-technical ops-wrap-anywhere" title="{{ $row->path }}">{{ $row->path }}</span>
                                <strong class="ops-numeric">{{ number_format($row->total) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="No route activity" description="No route or path counts are available for this window." />
                @endif
            </article>

            <article class="ops-panel">
                <div class="ops-panel-header">
                    <div>
                        <h2 class="ops-panel-title">HTTP response mix</h2>
                        <p class="ops-panel-description">Status-family distribution during the last 24 hours.</p>
                    </div>
                </div>
                @if ($statusDistribution->isNotEmpty())
                    <ul class="ops-list">
                        @foreach (['2xx', '3xx', '4xx', '5xx'] as $family)
                            @php
                                $familyTone = match ($family) {
                                    '2xx' => 'green',
                                    '3xx' => 'cyan',
                                    '4xx' => 'amber',
                                    '5xx' => 'red',
                                };
                            @endphp
                            <li class="ops-list-row">
                                <span class="ops-badge ops-badge--{{ $familyTone }}">{{ $family }}</span>
                                <strong class="ops-numeric">{{ number_format($statusDistribution[$family] ?? 0) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="No HTTP response data" description="Status-family context will appear with request telemetry." />
                @endif
            </article>
        </section>
    </div>

    @if ($requestTotal > 0)
        <x-slot:scripts>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                new Chart(document.getElementById('request-volume'), {
                    type: 'line',
                    data: {
                        labels: @json($hourlyTrend->pluck('label')),
                        datasets: [
                            {
                                label: 'Requests',
                                data: @json($hourlyTrend->pluck('count')),
                                borderColor: '#38bdf8',
                                backgroundColor: 'rgba(56, 189, 248, .1)',
                                borderWidth: 2,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                fill: true,
                                tension: .3,
                            },
                            {
                                label: 'Spike threshold',
                                data: Array({{ $hourlyTrend->count() }}).fill({{ $spikeThreshold }}),
                                borderColor: 'rgba(251, 191, 36, .72)',
                                borderDash: [6, 5],
                                borderWidth: 1,
                                pointRadius: 0,
                                fill: false,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { labels: { color: '#a8bfd0', boxWidth: 12 } },
                        },
                        scales: {
                            x: { ticks: { color: '#8eabc0', maxTicksLimit: 8, maxRotation: 0 }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { color: '#8eabc0', precision: 0 }, grid: { color: 'rgba(56, 189, 248, .08)' } },
                        },
                    },
                });
            </script>
        </x-slot:scripts>
    @endif
</x-layouts.app>
