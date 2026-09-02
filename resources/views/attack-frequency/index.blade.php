<x-layouts.app title="Attack Frequency - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Security monitoring</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Attack frequency</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-zinc-300">
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Tracked IPs: {{ count($attackFrequency) }}</span>
        </div>
    </div>

    <section class="mt-8 rounded-lg border border-zinc-800 bg-zinc-900 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Top attacking IPs</h2>
            <span class="text-xs uppercase tracking-wide text-zinc-500">7-day view</span>
        </div>
        <div class="h-80">
            <canvas id="attackFrequencyChart" aria-label="Attack frequency chart"></canvas>
        </div>
    </section>

    <section class="mt-8 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
        <div class="border-b border-zinc-800 px-5 py-4">
            <h2 class="text-lg font-semibold text-white">Frequency detail</h2>
        </div>
        <div class="divide-y divide-zinc-800">
            @forelse ($attackFrequency as $entry)
                <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-3">
                    <span class="text-zinc-300">{{ $entry['ip'] }}</span>
                    <span class="text-zinc-400">Request count: {{ $entry['count'] }}</span>
                    <span class="text-zinc-500 md:text-right">{{ $entry['count'] >= 10 ? 'High activity' : 'Normal activity' }}</span>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-zinc-400">No repeated attack patterns detected.</p>
            @endforelse
        </div>
    </section>

    <div class="mt-6 px-5 pb-5">
        {{ $attackFrequency->links() }}
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const attackLabels = @json(collect($attackFrequency->items())->pluck('ip')->all());
        const attackData = @json(collect($attackFrequency->items())->pluck('count')->all());

        new Chart(document.getElementById('attackFrequencyChart'), {
            type: 'bar',
            data: {
                labels: attackLabels,
                datasets: [{
                    label: 'Requests',
                    data: attackData,
                    borderRadius: 8,
                    backgroundColor: 'rgba(34, 211, 238, 0.7)',
                    borderColor: 'rgba(34, 211, 238, 1)',
                    borderWidth: 1,
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
