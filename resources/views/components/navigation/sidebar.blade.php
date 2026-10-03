<aside class="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-zinc-800 bg-zinc-950 lg:flex" aria-label="Primary navigation">
    <div class="border-b border-zinc-800 px-6 py-6"><x-intsec-brand /></div>
    <nav class="flex-1 space-y-5 overflow-y-auto px-4 py-6">
        <section><p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Overview</p><div class="mt-2"><x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')">Security Dashboard</x-navigation.nav-item></div></section>
        @if (auth()->user()?->isAdministrator())
            @foreach ([\App\Enums\MonitoringSource::HotelBooking, \App\Enums\MonitoringSource::Intsec] as $source)
                @php($activeSource = request()->routeIs('monitoring.*') && request()->route('source') === $source->value)
                <details class="group" @if($activeSource) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-xs font-medium uppercase tracking-wider text-zinc-500 hover:bg-zinc-900 hover:text-zinc-300"><span>{{ $source->label() }} Monitoring</span><span class="transition group-open:rotate-90">›</span></summary>
                    <div class="mt-1 space-y-1 pl-2">
                        <x-navigation.nav-item :href="route('monitoring.overview', $source->value)" :active="request()->routeIs('monitoring.overview') && $activeSource">Overview</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.login-activity', $source->value)" :active="request()->routeIs('monitoring.login-activity') && $activeSource">Login Activity</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.ip-locations', $source->value)" :active="request()->routeIs('monitoring.ip-locations') && $activeSource">IP Monitoring</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.request-activities', $source->value)" :active="request()->routeIs('monitoring.request-activities') && $activeSource">Request Activity</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.attack-frequency', $source->value)" :active="request()->routeIs('monitoring.attack-frequency') && $activeSource">Request Frequency</x-navigation.nav-item>
                        <x-navigation.nav-item :href="route('monitoring.ddos-monitoring', $source->value)" :active="request()->routeIs('monitoring.ddos-monitoring') && $activeSource">DDoS Monitoring</x-navigation.nav-item>
                        @if ($source === \App\Enums\MonitoringSource::HotelBooking)
                            <x-navigation.nav-item :href="route('monitoring.security-events', $source->value)" :active="request()->routeIs('monitoring.security-events') && $activeSource">Security Events</x-navigation.nav-item>
                        @else
                            <x-navigation.nav-item :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')">Audit Logs</x-navigation.nav-item>
                        @endif
                    </div>
                </details>
            @endforeach
            <details class="group" @if(request()->routeIs('alerts.*','incidents.*','ip-management.*')) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-xs font-medium uppercase tracking-wider text-zinc-500 hover:bg-zinc-900 hover:text-zinc-300"><span>Security Operations</span><span class="transition group-open:rotate-90">›</span></summary>
                <div class="mt-1 space-y-1 pl-2"><x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Alerts</x-navigation.nav-item><x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')">Incidents</x-navigation.nav-item><x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')">Blocked IPs</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">Detection Rules</x-navigation.nav-item></div>
            </details>
            <details class="group" @if(request()->routeIs('admin.index','admin.settings','users.*')) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-xs font-medium uppercase tracking-wider text-zinc-500 hover:bg-zinc-900 hover:text-zinc-300"><span>System</span><span class="transition group-open:rotate-90">›</span></summary>
                <div class="mt-1 space-y-1 pl-2"><x-navigation.nav-item :href="route('users.index')" :active="request()->routeIs('users.*')">Users</x-navigation.nav-item><x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">Settings</x-navigation.nav-item></div>
            </details>
        @endif
        <section><p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Account</p><div class="mt-2"><x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile & password</x-navigation.nav-item></div></section>
    </nav>
    <div class="border-t border-zinc-800 p-4"><div class="flex items-center gap-3 px-3 py-2"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-sm font-semibold text-cyan-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div class="min-w-0"><p class="truncate text-sm font-medium text-zinc-200">{{ auth()->user()->name }}</p><p class="truncate text-xs text-zinc-500">{{ auth()->user()->isAdministrator() ? 'Administrator' : 'Standard user' }}</p></div></div><form method="POST" action="{{ route('logout') }}" class="mt-2" onsubmit="return confirm('Are you sure you want to sign out of INTSEC?');">@csrf<button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-400 transition hover:bg-zinc-900 hover:text-white">Sign out</button></form></div>
</aside>
