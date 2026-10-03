<x-layouts.app :title="$monitoringSource->label().' Monitoring - INTSEC'" wide realtime-entities="*" :realtime-source="$monitoringSource->value">
    <header><p class="text-xs uppercase tracking-[0.2em] text-cyan-400">{{ $monitoringSource->label() }} monitoring</p><h1 class="mt-2 text-3xl font-semibold text-white">Overview</h1><p class="mt-2 text-sm text-zinc-400">Source-isolated telemetry and security workflow for <span class="font-mono">{{ $monitoringSource->value }}</span>.</p></header>
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-security.metric-card label="Requests" :value="number_format($requestCount)" />
        <x-security.metric-card label="Authentication" :value="number_format($authenticationCount)" tone="zinc" />
        <x-security.metric-card label="Security events" :value="number_format($securityEventCount)" tone="amber" />
        <x-security.metric-card label="Open alerts" :value="number_format($openAlertCount)" tone="red" />
        <x-security.metric-card label="Open incidents" :value="number_format($openIncidentCount)" tone="red" />
    </section>
    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><h2 class="font-semibold text-white">Recent requests</h2><div class="mt-3 divide-y divide-zinc-800">@forelse ($recentRequests as $activity)<div class="flex justify-between gap-4 py-3 text-sm"><span class="min-w-0 truncate font-mono text-zinc-300">{{ $activity->method }} {{ $activity->path }}</span><span class="shrink-0 text-zinc-500">{{ $activity->status_code }}</span></div>@empty<p class="py-6 text-sm text-zinc-500">No {{ $monitoringSource->label() }} request telemetry.</p>@endforelse</div></div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-5"><h2 class="font-semibold text-white">Recent security events</h2><div class="mt-3 divide-y divide-zinc-800">@forelse ($recentEvents as $event)<div class="flex justify-between gap-4 py-3 text-sm"><span class="min-w-0 truncate text-zinc-300">{{ $event->title }}</span><x-security.severity-badge :severity="$event->severity" /></div>@empty<p class="py-6 text-sm text-zinc-500">No {{ $monitoringSource->label() }} security events.</p>@endforelse</div></div>
    </section>
</x-layouts.app>
