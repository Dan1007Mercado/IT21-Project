<aside id="intsec-sidebar" class="app-sidebar fixed inset-y-0 left-0 hidden w-64 flex-col border-r border-zinc-800 bg-zinc-950 lg:flex" aria-label="Primary navigation">
    <div class="app-sidebar-brand border-b border-zinc-800 px-6 py-6"><x-intsec-brand /></div>
    <button type="button" class="app-sidebar-toggle" data-sidebar-toggle aria-expanded="true" aria-controls="intsec-sidebar" aria-label="Collapse sidebar"><x-navigation.icon name="collapse" /><span class="sr-only">Collapse sidebar</span></button>
    <nav class="app-sidebar-nav flex-1 space-y-5 overflow-y-auto px-4 py-6">
        <section><p class="sidebar-section-label">Overview</p><div class="mt-2"><x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard">Security Dashboard</x-navigation.nav-item></div></section>

        @if (auth()->user()?->isAdministrator())
            @foreach ([\App\Enums\MonitoringSource::HotelBooking, \App\Enums\MonitoringSource::Intsec] as $source)
                @php
                    $activeSource = request()->routeIs('monitoring.*') && request()->route('source') === $source->value;
                @endphp
                <details class="group" @if($activeSource) open @endif>
                    <summary class="sidebar-group-summary"><span class="sidebar-group-copy"><x-navigation.icon name="monitor" />{{ $source->label() }} Monitoring</span><x-navigation.icon name="chevron" class="sidebar-group-chevron" /></summary>
                    <div class="mt-1 space-y-1 pl-2">
                        <x-navigation.nav-item :href="route('monitoring.overview', $source->value)" :active="request()->routeIs('monitoring.overview') && $activeSource" icon="overview">Overview</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.login-activity', $source->value)" :active="request()->routeIs('monitoring.login-activity') && $activeSource" icon="login">Login Activity</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.ip-locations', $source->value)" :active="request()->routeIs('monitoring.ip-locations') && $activeSource" icon="map">IP Monitoring</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.request-activities', $source->value)" :active="request()->routeIs('monitoring.request-activities') && $activeSource" icon="requests">Request Activity</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.attack-frequency', $source->value)" :active="request()->routeIs('monitoring.attack-frequency') && $activeSource" icon="frequency">Request Frequency</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.ddos-monitoring', $source->value)" :active="request()->routeIs('monitoring.ddos-monitoring') && $activeSource" icon="spikes">Application Spikes</x-navigation.nav-item>
                        @if ($source === \App\Enums\MonitoringSource::HotelBooking)
                            <x-navigation.nav-item :href="route('monitoring.security-events', $source->value)" :active="request()->routeIs('monitoring.security-events') && $activeSource" icon="events">Security Events</x-navigation.nav-item>
                        @else
                            <x-navigation.nav-item :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')" icon="audit">Audit Logs</x-navigation.nav-item>
                        @endif
                    </div>
                </details>
            @endforeach

            <details class="group" @if(request()->routeIs('alerts.*','incidents.*','ip-management.*')) open @endif>
                <summary class="sidebar-group-summary"><span class="sidebar-group-copy"><x-navigation.icon name="operations" />Security Operations</span><x-navigation.icon name="chevron" class="sidebar-group-chevron" /></summary>
                <div class="mt-1 space-y-1 pl-2">
                    <x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')" icon="alerts">Alerts</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')" icon="incidents">Incidents</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')" icon="ip">IP Management</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')" icon="rules">Detection Rules</x-navigation.nav-item>
                </div>
            </details>

            <details class="group" @if(request()->routeIs('admin.index','admin.settings','users.*')) open @endif>
                <summary class="sidebar-group-summary"><span class="sidebar-group-copy"><x-navigation.icon name="system" />System</span><x-navigation.icon name="chevron" class="sidebar-group-chevron" /></summary>
                <div class="mt-1 space-y-1 pl-2">
                    <x-navigation.nav-item :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">Users</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')" icon="settings">Settings</x-navigation.nav-item>
                </div>
            </details>
        @endif

        <section><p class="sidebar-section-label">Account</p><div class="mt-2"><x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="profile">Profile & password</x-navigation.nav-item></div></section>
    </nav>

    <div class="sidebar-account border-t border-zinc-800 p-4">
        <div class="flex items-center gap-3 px-3 py-2"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-sm font-semibold text-cyan-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div class="min-w-0"><p class="truncate text-sm font-medium text-zinc-200">{{ auth()->user()->name }}</p><p class="truncate text-xs text-zinc-500">{{ auth()->user()->isAdministrator() ? 'Administrator' : 'Standard user' }}</p></div></div>
        <form method="POST" action="{{ route('logout') }}" class="mt-2" onsubmit="return confirm('Are you sure you want to sign out of INTSEC?');">@csrf<button type="submit" class="sidebar-signout"><x-navigation.icon name="logout" /><span>Sign out</span></button></form>
    </div>
</aside>
