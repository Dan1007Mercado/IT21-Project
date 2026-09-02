<x-layouts.app title="Dashboard - INTSEC">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        .font-mono-data { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    </style>

    <div class="flex flex-col gap-1 border-b border-zinc-800 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="font-mono-data text-xs text-cyan-400">{{ auth()->user()->name }} · session active</p>
            <h1 class="mt-2 text-2xl font-semibold text-white">User dashboard</h1>
        </div>
        <div class="flex items-center gap-2 border border-zinc-800 px-3 py-1.5 font-mono-data text-xs text-zinc-400">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            {{ auth()->user()->role === 'administrator' ? 'ADMINISTRATOR' : 'STANDARD USER' }}
        </div>
    </div>

    {{-- Stat rail --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950/70 shadow-lg shadow-black/20">
        <div class="grid divide-y divide-zinc-800 md:grid-cols-3 md:divide-x md:divide-y-0">
            <div class="p-5">
                <p class="text-xs uppercase tracking-[0.2em] text-zinc-500">Total attempts</p>
                <p class="font-mono-data mt-3 text-3xl font-semibold text-white">{{ number_format($successfulLogins + $failedAttempts) }}</p>
            </div>
            <div class="p-5">
                <p class="text-xs uppercase tracking-[0.2em] text-zinc-500">Successful</p>
                <p class="font-mono-data mt-3 text-3xl font-semibold text-emerald-300">{{ number_format($successfulLogins) }}</p>
            </div>
            <div class="p-5">
                <p class="text-xs uppercase tracking-[0.2em] text-zinc-500">Failed</p>
                <p class="font-mono-data mt-3 text-3xl font-semibold text-amber-300">{{ number_format($failedAttempts) }}</p>
            </div>
        </div>
    </div>

    {{-- Hero: activity trend + status mix --}}
    <div class="mt-px grid border border-t-0 border-zinc-800 lg:grid-cols-[1.7fr_1fr]">
        <section class="border-b border-zinc-800 p-6 lg:border-b-0 lg:border-r">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-white">7-day activity trend</h2>
                    <p class="text-sm text-zinc-500">Login attempts recorded per day</p>
                </div>
                <a href="{{ route('login-activity') }}" class="font-mono-data text-xs text-cyan-400 hover:text-cyan-300">view log →</a>
            </div>
            <div class="h-64">
                <canvas id="activityTrendChart" aria-label="Authentication activity trend chart"></canvas>
            </div>
        </section>

        <section class="p-6">
            <div class="mb-5">
                <h2 class="font-semibold text-white">Login status mix</h2>
                <p class="text-sm text-zinc-500">Successful vs. failed vs. logouts</p>
            </div>
            <div class="h-64">
                <canvas id="statusMixChart" aria-label="Authentication status mix chart"></canvas>
            </div>
        </section>
    </div>

    {{-- Tool links --}}
    <div class="mt-8 grid gap-px border border-zinc-800 bg-zinc-800 md:grid-cols-3">
        <a href="{{ route('ip-locations') }}" class="group bg-zinc-950 p-5 transition hover:bg-zinc-900">
            <p class="font-mono-data text-xs text-zinc-600">01</p>
            <h3 class="mt-2 font-medium text-white">IP locations</h3>
            <p class="mt-1 text-sm text-zinc-500">Approximate public IP geolocation</p>
            <span class="mt-4 inline-flex font-mono-data text-xs text-cyan-400 opacity-0 transition group-hover:opacity-100">view map →</span>
        </a>

        <a href="{{ route('ddos-monitoring') }}" class="group bg-zinc-950 p-5 transition hover:bg-zinc-900">
            <p class="font-mono-data text-xs text-zinc-600">02</p>
            <h3 class="mt-2 font-medium text-white">DDoS / spikes</h3>
            <p class="mt-1 text-sm text-zinc-500">Request surge monitoring</p>
            <span class="mt-4 inline-flex font-mono-data text-xs text-cyan-400 opacity-0 transition group-hover:opacity-100">view details →</span>
        </a>

        <a href="{{ route('attack-frequency') }}" class="group bg-zinc-950 p-5 transition hover:bg-zinc-900">
            <p class="font-mono-data text-xs text-zinc-600">03</p>
            <h3 class="mt-2 font-medium text-white">Attack frequency</h3>
            <p class="mt-1 text-sm text-zinc-500">Top offending IPs by volume</p>
            <span class="mt-4 inline-flex font-mono-data text-xs text-cyan-400 opacity-0 transition group-hover:opacity-100">view analysis →</span>
        </a>
    </div>

    {{-- Recent activity --}}
    <section class="mt-8 border border-zinc-800">
        <div class="border-b border-zinc-800 px-5 py-4">
            <h2 class="font-semibold text-white">Recent authentication activity</h2>
        </div>
        <div class="divide-y divide-zinc-800">
            @forelse ($recentActivity as $log)
                <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-4">
                    <span class="text-zinc-300">{{ ucfirst($log->action) }}</span>
                    <span class="flex items-center gap-2 {{ $log->status === 'successful' ? 'text-emerald-300' : 'text-red-300' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $log->status === 'successful' ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                        {{ ucfirst($log->status) }}
                    </span>
                    <span class="font-mono-data text-zinc-400">{{ $log->ip_address ?? 'Unknown IP' }}</span>
                    <span class="font-mono-data text-zinc-500 md:text-right">{{ $log->occurred_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-zinc-500">No authentication activity has been recorded yet.</p>
            @endforelse
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const trendLabels = @json(collect($activityTrend)->pluck('label')->all());
        const trendData = @json(collect($activityTrend)->pluck('count')->all());

        const statusLabels = ['Successful', 'Failed', 'Logouts'];
        const statusData = [
            {{ $statusBreakdown['successful'] ?? 0 }},
            {{ $statusBreakdown['failed'] ?? 0 }},
            {{ $statusBreakdown['logout'] ?? 0 }},
        ];

        new Chart(document.getElementById('activityTrendChart'), {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Authentication events',
                    data: trendData,
                    borderRadius: 2,
                    backgroundColor: 'rgba(34, 211, 238, 0.7)',
                    borderColor: 'rgba(34, 211, 238, 1)',
                    borderWidth: 1,
                    maxBarThickness: 24,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#a1a1aa', font: { family: 'JetBrains Mono' } }, grid: { display: false } },
                    y: {
                        ticks: { color: '#a1a1aa', precision: 0, font: { family: 'JetBrains Mono' } },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                        beginAtZero: true,
                    },
                },
            },
        });

        new Chart(document.getElementById('statusMixChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#22d3ee', '#f59e0b', '#a78bfa'],
                    borderColor: '#09090b',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#d4d4d8', padding: 16, usePointStyle: true, boxWidth: 8, font: { family: 'JetBrains Mono', size: 11 } },
                    },
                },
            },
        });
    </script>
</x-layouts.app>