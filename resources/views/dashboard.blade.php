<x-layouts.app title="Security Overview - INTSEC" wide>
    <header class="flex flex-col gap-4 border-b border-zinc-800 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-medium uppercase tracking-[0.2em] text-cyan-400">INTSEC operations</p><h1 class="mt-2 text-3xl font-semibold text-white">Security overview</h1><p class="mt-2 text-sm text-zinc-400">Read-only operational visibility from persisted telemetry and security records.</p></div>
        <nav class="flex gap-3 text-xs" aria-label="Dashboard range">@foreach (['today' => 'Today','7d' => '7 days','30d' => '30 days','90d' => '90 days'] as $value => $label)<a href="{{ route('dashboard', ['range' => $value]) }}" class="{{ $activityRange === $value ? 'text-cyan-300' : 'text-zinc-500 hover:text-zinc-300' }}">{{ $label }}</a>@endforeach</nav>
    </header>

    @if ($isAdministrator)
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Security metrics">
            <x-security.metric-card label="INTSEC requests" :value="number_format($sourceRequestCounts['intsec'] ?? 0)" />
            <x-security.metric-card label="Hotel requests" :value="number_format($sourceRequestCounts['hotel-booking'] ?? 0)" />
            <x-security.metric-card label="Global security events" :value="number_format($totalSecurityEvents)" />
            <x-security.metric-card label="Global active alerts" :value="number_format($openSecurityAlertCount)" tone="amber" />
            <x-security.metric-card label="Open incidents" :value="number_format($openIncidentCount)" tone="red" />
            <x-security.metric-card label="Blocked IP policies" :value="number_format($blockedIpCount)" tone="emerald" />
        </section>
    @else
        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <x-security.metric-card label="Successful logins" :value="number_format($successfulLogins)" tone="emerald" />
            <x-security.metric-card label="Failed attempts" :value="number_format($failedAttempts)" tone="amber" />
            <x-security.metric-card label="Recorded account events" :value="number_format($successfulLogins + $failedAttempts + $statusBreakdown['logout'])" />
        </section>
    @endif

    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><div><h2 class="font-semibold text-white">INTSEC Request activity trend</h2><p class="text-sm text-zinc-500">Internal INTSEC requests from RequestActivity</p></div><div class="mt-5 h-64"><canvas id="requestTrend"></canvas></div></div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><div><h2 class="font-semibold text-white">Authentication activity trend</h2><p class="text-sm text-zinc-500">Authentication telemetry only</p></div><div class="mt-5 h-64"><canvas id="authTrend"></canvas></div></div>
    </section>

    @if ($isAdministrator)
        <section class="mt-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><h2 class="font-semibold text-white">Recent important alerts</h2><div class="mt-3 divide-y divide-zinc-800">@forelse ($recentSecurityAlerts as $alert)<a href="{{ route('alerts.show', $alert) }}" class="block py-3"><div class="flex justify-between gap-3"><span class="text-sm text-zinc-200">{{ $alert->title }}</span><x-security.severity-badge :severity="$alert->severity" /></div><p class="mt-1 text-xs text-zinc-500">{{ $alert->source_ip ?? 'No source IP' }} · {{ $alert->occurred_at?->diffForHumans() }}</p></a>@empty<p class="py-6 text-sm text-zinc-500">No active alerts.</p>@endforelse</div></div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><h2 class="font-semibold text-white">Top active IPs</h2><div class="mt-3 divide-y divide-zinc-800">@forelse ($topActiveIps as $entry)<a href="{{ route('attack-frequency', ['ip' => $entry->ip_address]) }}" class="flex justify-between gap-3 py-3 text-sm"><span class="font-mono text-zinc-300">{{ $entry->ip_address }}</span><span class="text-zinc-500">{{ number_format($entry->request_count) }} requests</span></a>@empty<p class="py-6 text-sm text-zinc-500">No request telemetry yet.</p>@endforelse</div></div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><h2 class="font-semibold text-white">Recent incidents</h2><div class="mt-3 divide-y divide-zinc-800">@forelse ($recentIncidents as $incident)<a href="{{ route('incidents.show', $incident) }}" class="block py-3"><div class="flex justify-between gap-3"><span class="text-sm text-zinc-200">{{ $incident->title }}</span><x-security.status-badge :status="$incident->status" /></div><p class="mt-1 text-xs text-zinc-500">{{ $incident->incident_id }} · {{ $incident->last_detected_at?->diffForHumans() }}</p></a>@empty<p class="py-6 text-sm text-zinc-500">No incidents.</p>@endforelse</div></div>
        </section>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chartOptions = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{ticks:{color:'#71717a'},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#71717a',precision:0},grid:{color:'rgba(255,255,255,.06)'}}}};
        new Chart(document.getElementById('requestTrend'), {type:'line',data:{labels:@json(collect($requestTrend)->pluck('label')),datasets:[{data:@json(collect($requestTrend)->pluck('count')),borderColor:'#22d3ee',backgroundColor:'rgba(34,211,238,.12)',fill:true,tension:.3}]},options:chartOptions});
        new Chart(document.getElementById('authTrend'), {type:'line',data:{labels:@json(collect($authenticationTrend)->pluck('label')),datasets:[{data:@json(collect($authenticationTrend)->pluck('count')),borderColor:'#34d399',backgroundColor:'rgba(52,211,153,.1)',fill:true,tension:.3}]},options:chartOptions});
    </script>
</x-layouts.app>
