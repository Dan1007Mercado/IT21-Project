<header class="sticky top-0 z-30 border-b border-zinc-800 bg-zinc-950/95 px-4 py-3 backdrop-blur lg:hidden">
    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><x-intsec-brand compact /><span class="mobile-menu-button"><x-navigation.icon name="menu" />Menu</span></summary>
        <nav class="absolute inset-x-0 top-[calc(100%+0.75rem)] max-h-[75vh] space-y-2 overflow-y-auto rounded-md border border-zinc-800 bg-zinc-950 p-3 shadow-2xl" aria-label="Mobile navigation">
            <x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard">Security Dashboard</x-navigation.nav-item>
            @if (auth()->user()?->isAdministrator())
                @foreach ([\App\Enums\MonitoringSource::HotelBooking, \App\Enums\MonitoringSource::Intsec] as $source)
                    @php
                        $activeSource = request()->routeIs('monitoring.*') && request()->route('source') === $source->value;
                    @endphp
                    <details class="rounded-md border border-zinc-800 p-2">
                        <summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">{{ $source->label() }} Monitoring</summary>
                        <div class="mt-2 space-y-1">
                            <x-navigation.nav-item :href="route('monitoring.overview', $source->value)" :active="request()->routeIs('monitoring.overview') && $activeSource" icon="overview">Overview</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.login-activity', $source->value)" :active="request()->routeIs('monitoring.login-activity') && $activeSource" icon="login">Login Activity</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.ip-locations', $source->value)" :active="request()->routeIs('monitoring.ip-locations') && $activeSource" icon="map">IP Monitoring</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.request-activities', $source->value)" :active="request()->routeIs('monitoring.request-activities') && $activeSource" icon="requests">Request Activity</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.attack-frequency', $source->value)" :active="request()->routeIs('monitoring.attack-frequency') && $activeSource" icon="frequency">Request Frequency</x-navigation.nav-item>
                            <x-navigation.nav-item :href="route('monitoring.ddos-monitoring', $source->value)" :active="request()->routeIs('monitoring.ddos-monitoring') && $activeSource" icon="spikes">Application Spikes</x-navigation.nav-item>
                            @if ($source === \App\Enums\MonitoringSource::HotelBooking)<x-navigation.nav-item :href="route('monitoring.security-events', $source->value)" :active="request()->routeIs('monitoring.security-events') && $activeSource" icon="events">Security Events</x-navigation.nav-item>@else<x-navigation.nav-item :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')" icon="audit">Audit Logs</x-navigation.nav-item>@endif
                        </div>
                    </details>
                @endforeach
                <details class="rounded-md border border-zinc-800 p-2"><summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">Security Operations</summary><div class="mt-2 space-y-1"><x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')" icon="alerts">Alerts</x-navigation.nav-item><x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')" icon="incidents">Incidents</x-navigation.nav-item><x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')" icon="ip">IP Management</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')" icon="rules">Detection Rules</x-navigation.nav-item></div></details>
                <details class="rounded-md border border-zinc-800 p-2"><summary class="cursor-pointer text-xs font-medium uppercase tracking-wider text-zinc-400">System</summary><div class="mt-2 space-y-1"><x-navigation.nav-item :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">Users</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')" icon="settings">Settings</x-navigation.nav-item></div></details>
            @endif
            <x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="profile">Profile & password</x-navigation.nav-item>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-800 pt-2">@csrf<button class="sidebar-signout"><x-navigation.icon name="logout" /><span>Sign out</span></button></form>
        </nav>
    </details>
</header>
