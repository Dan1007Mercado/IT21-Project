<aside class="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-zinc-800 bg-zinc-950 lg:flex" aria-label="Primary navigation">
    <div class="border-b border-zinc-800 px-6 py-6"><x-intsec-brand /></div>
    <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-6">
        <section><p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Overview</p><div class="mt-2"><x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')">Security overview</x-navigation.nav-item></div></section>
        <section>
            <p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Monitoring</p>
            <div class="mt-2 space-y-1">
                <x-navigation.nav-item :href="route('ddos-monitoring')" :active="request()->routeIs('ddos-monitoring')">Request volume</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('attack-frequency')" :active="request()->routeIs('attack-frequency')">IP request frequency</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('ip-locations')" :active="request()->routeIs('ip-locations')">IP intelligence</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('login-activity')" :active="request()->routeIs('login-activity')">Login activity</x-navigation.nav-item>
            </div>
        </section>
        @if (auth()->user()?->isAdministrator())
            <section>
                <p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Incident response</p>
                <div class="mt-2 space-y-1">
                    <x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Security alerts</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')">Incidents</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')">IP policies</x-navigation.nav-item>
                </div>
            </section>
            <section>
                <p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Administration</p>
                <div class="mt-2 space-y-1">
                    <x-navigation.nav-item :href="route('admin.index')" :active="request()->routeIs('admin.index')">Operations workspace</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">System settings</x-navigation.nav-item>
                    <x-navigation.nav-item :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')">Audit logs</x-navigation.nav-item>
                </div>
            </section>
        @endif
        <section><p class="px-3 text-xs font-medium uppercase tracking-wider text-zinc-600">Account</p><div class="mt-2"><x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile & password</x-navigation.nav-item></div></section>
    </nav>
    <div class="border-t border-zinc-800 p-4">
        <div class="flex items-center gap-3 px-3 py-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-sm font-semibold text-cyan-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="min-w-0"><p class="truncate text-sm font-medium text-zinc-200">{{ auth()->user()->name }}</p><p class="truncate text-xs text-zinc-500">{{ auth()->user()->isAdministrator() ? 'Administrator' : 'Standard user' }}</p></div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-2" onsubmit="return confirm('Are you sure you want to sign out of INTSEC?');">@csrf<button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-400 transition hover:bg-zinc-900 hover:text-white">Sign out</button></form>
    </div>
</aside>
