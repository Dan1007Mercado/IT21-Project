<header class="sticky top-0 z-30 border-b border-zinc-800 bg-zinc-950/95 px-4 py-3 backdrop-blur lg:hidden">
    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><x-intsec-brand compact /><span class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-300">Menu</span></summary>
        <nav class="absolute inset-x-0 top-[calc(100%+0.75rem)] max-h-[75vh] space-y-2 overflow-y-auto rounded-md border border-zinc-800 bg-zinc-950 p-3 shadow-2xl" aria-label="Mobile navigation">
            <x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')">Security Dashboard</x-navigation.nav-item>
            @if (auth()->user()?->isAdministrator())
                @foreach ([\App\Enums\MonitoringSource::HotelBooking, \App\Enums\MonitoringSource::Intsec] as $source)
                    <details class="rounded-md border border-zinc-800 p-2">
                        <summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">{{ $source->label() }} Monitoring</summary>
                        <div class="mt-2 space-y-1">
                            <x-navigation.nav-item :href="route('monitoring.overview', $source->value)" :active="false">Overview</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.login-activity', $source->value)" :active="false">Login Activity</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.ip-locations', $source->value)" :active="false">IP Monitoring</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.request-activities', $source->value)" :active="false">Request Activity</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.attack-frequency', $source->value)" :active="false">Request Frequency</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.ddos-monitoring', $source->value)" :active="false">DDoS Monitoring</x-navigation.nav-item>
                            @if ($source === \App\Enums\MonitoringSource::HotelBooking)<x-navigation.nav-item :href="route('monitoring.security-events', $source->value)" :active="false">Security Events</x-navigation.nav-item>@else<x-navigation.nav-item :href="route('admin.audit-logs')" :active="false">Audit Logs</x-navigation.nav-item>@endif
                        </div>
                    </details>
                @endforeach
                <details class="rounded-md border border-zinc-800 p-2"><summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">Security Operations</summary><div class="mt-2 space-y-1"><x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Alerts</x-navigation.nav-item><x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')">Incidents</x-navigation.nav-item><x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')">Blocked IPs</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="false">Detection Rules</x-navigation.nav-item></div></details>
                <details class="rounded-md border border-zinc-800 p-2"><summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">System</summary><div class="mt-2 space-y-1"><x-navigation.nav-item :href="route('users.index')" :active="request()->routeIs('users.*')">Users</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">Settings</x-navigation.nav-item></div></details>
            @endif
            <x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile & password</x-navigation.nav-item>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-800 pt-2">@csrf<button class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-300 hover:bg-zinc-900">Sign out</button></form>
        </nav>
    </details>
</header>
