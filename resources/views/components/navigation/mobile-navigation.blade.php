<header class="sticky top-0 z-30 border-b border-zinc-800 bg-zinc-950/95 px-4 py-3 backdrop-blur lg:hidden">
    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><x-intsec-brand compact /><span class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-300">Menu</span></summary>
        <nav class="absolute inset-x-0 top-[calc(100%+0.75rem)] space-y-1 rounded-md border border-zinc-800 bg-zinc-950 p-3 shadow-2xl" aria-label="Mobile navigation">
            <x-navigation.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')">Security overview</x-navigation.nav-item>
            <x-navigation.nav-item :href="route('ddos-monitoring')" :active="request()->routeIs('ddos-monitoring')">Request volume</x-navigation.nav-item>
            <x-navigation.nav-item :href="route('attack-frequency')" :active="request()->routeIs('attack-frequency')">IP request frequency</x-navigation.nav-item>
            <x-navigation.nav-item :href="route('ip-locations')" :active="request()->routeIs('ip-locations')">IP intelligence</x-navigation.nav-item>
            <x-navigation.nav-item :href="route('login-activity')" :active="request()->routeIs('login-activity')">Login activity</x-navigation.nav-item>
            @if (auth()->user()?->isAdministrator())
                <x-navigation.nav-item :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Security alerts</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('incidents.index')" :active="request()->routeIs('incidents.*')">Incidents</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('ip-management.index')" :active="request()->routeIs('ip-management.*')">IP policies</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">System settings</x-navigation.nav-item>
                <x-navigation.nav-item :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')">Audit logs</x-navigation.nav-item>
            @endif
            <x-navigation.nav-item :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile & password</x-navigation.nav-item>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-800 pt-2">@csrf<button class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-300 hover:bg-zinc-900">Sign out</button></form>
        </nav>
    </details>
</header>
