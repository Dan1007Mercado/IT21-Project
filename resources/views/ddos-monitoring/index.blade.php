<x-layouts.app title="DDoS / Request Spikes - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Security monitoring</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">DDoS / request spikes</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-zinc-300">
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Current requests: {{ $currentRequests ?? 0 }}</span>
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Peak requests: {{ $peakRequests ?? 0 }}</span>
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Suspicious spikes: {{ $suspiciousSpikes ?? 0 }}</span>
        </div>
    </div>

    <section class="mt-8 rounded-lg border border-zinc-800 bg-zinc-900 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Request volume</h2>
            <span class="text-xs uppercase tracking-wide text-zinc-500">7-day watch</span>
        </div>
        <div class="h-80">
            <canvas id="ddosMonitoringChart" aria-label="DDoS monitoring chart"></canvas>
        </div>
    </section>

    <section class="mt-8 rounded-lg border border-zinc-800 bg-zinc-900 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Traffic pattern</h2>
            <span class="text-xs uppercase tracking-wide text-zinc-500">By hour</span>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            @foreach ($hourlyTrend as $bucket)
                <div class="rounded-md border border-zinc-800 bg-zinc-950/60 p-3">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-zinc-200">{{ $bucket['label'] }}</span>
                        <span class="rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-300">{{ $bucket['count'] }} req</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-zinc-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-red-500 to-orange-400" style="width: {{ $peakRequests > 0 ? min(($bucket['count'] / $peakRequests) * 100, 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const requestLabels = @json(collect($hourlyTrend)->pluck('label')->all());
        const requestData = @json(collect($hourlyTrend)->pluck('count')->all());

        new Chart(document.getElementById('ddosMonitoringChart'), {
            type: 'line',
            data: {
                labels: requestLabels,
                datasets: [{
                    label: 'Requests per hour',
                    data: requestData,
                    borderColor: '#f87171',
                    backgroundColor: 'rgba(248, 113, 113, 0.25)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        ticks: { color: '#a1a1aa' },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#a1a1aa', precision: 0 },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                    },
                },
            },
        });
    </script>
</x-layouts.app>
