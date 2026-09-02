<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name', 'INTSEC') }}</title>
        <x-app-assets />
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="min-h-screen lg:pl-72">
            <aside class="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-zinc-800 bg-zinc-950 text-zinc-100 lg:flex" aria-label="Primary navigation">
                <div class="border-b border-zinc-800 px-6 py-6">
                    <x-intsec-brand />
                </div>

                <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-6 text-sm">
                    <section>
                        <p class="px-3 text-xs font-medium uppercase text-zinc-600">Main</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('dashboard') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('dashboard'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('dashboard')])>Dashboard</a>
                        </div>
                    </section>

                    <section>
                        <p class="px-3 text-xs font-medium uppercase text-zinc-600">Security monitoring</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('ddos-monitoring') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('ddos-monitoring'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('ddos-monitoring')])>DDoS / request spikes</a>
                            <a href="{{ route('attack-frequency') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('attack-frequency'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('attack-frequency')])>Attack frequency</a>
                        </div>
                    </section>

                    <section>
                        <p class="px-3 text-xs font-medium uppercase text-zinc-600">IP intelligence</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('ip-locations') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('ip-locations'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('ip-locations')])>IP locations</a>
                        </div>
                    </section>

                    <section>
                        <p class="px-3 text-xs font-medium uppercase text-zinc-600">Authentication</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('login-activity') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('login-activity'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('login-activity')])>Login activity</a>
                        </div>
                    </section>

                    <section>
                        <p class="px-3 text-xs font-medium uppercase text-zinc-600">Account</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('profile.edit') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('profile.*'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('profile.*')])>Profile & password</a>
                        </div>
                    </section>

                    @if (auth()->user()?->isAdministrator())
                        <section>
                            <p class="px-3 text-xs font-medium uppercase text-zinc-600">Administration</p>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('admin.index') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('admin.index'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('admin.index')])>Admin monitoring</a>
                                <a href="{{ route('incidents.index') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('incidents.*'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('incidents.*')])>Incident management</a>
                                <a href="{{ route('admin.settings') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('admin.settings'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('admin.settings')])>System settings</a>
                                <a href="{{ route('admin.audit-logs') }}" @class(['block rounded-md px-3 py-2.5 font-medium transition', 'bg-cyan-400/10 text-cyan-200' => request()->routeIs('admin.audit-logs'), 'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('admin.audit-logs')])>Audit logs</a>
                            </div>
                        </section>
                    @endif
                </nav>

                <div class="border-t border-zinc-800 p-4">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-sm font-semibold text-cyan-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-200">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ auth()->user()->isAdministrator() ? 'Administrator' : 'Standard user' }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2" onsubmit="return confirm('Are you sure you want to sign out of INTSEC?');">
                        @csrf
                        <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-400 transition hover:bg-zinc-900 hover:text-white">Sign out</button>
                    </form>
                </div>
            </aside>

            <header class="border-b border-zinc-800 bg-zinc-950/95 px-4 py-3 backdrop-blur-sm lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <details class="relative flex-1">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                            <x-intsec-brand compact />
                            <span class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-300">Menu</span>
                        </summary>
                        <nav class="absolute inset-x-0 top-[calc(100%+0.75rem)] z-20 space-y-1 rounded-md border border-zinc-800 bg-zinc-950 p-3 shadow-2xl">
                            <a href="{{ route('dashboard') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Dashboard</a>
                            <a href="{{ route('ddos-monitoring') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">DDoS / request spikes</a>
                            <a href="{{ route('attack-frequency') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Attack frequency</a>
                            <a href="{{ route('ip-locations') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">IP locations</a>
                            <a href="{{ route('login-activity') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Login activity</a>
                            <a href="{{ route('profile.edit') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Profile & password</a>
                            @if (auth()->user()?->isAdministrator())
                                <a href="{{ route('admin.index') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Admin monitoring</a>
                                <a href="{{ route('incidents.index') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Incident management</a>
                                <a href="{{ route('admin.settings') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">System settings</a>
                                <a href="{{ route('admin.audit-logs') }}" class="block rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Audit logs</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-800 pt-2" onsubmit="return confirm('Are you sure you want to sign out of INTSEC?');">
                                @csrf
                                <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white">Sign out</button>
                            </form>
                        </nav>
                    </details>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
                @if (session('status') === 'profile-updated')
                    <div class="mb-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">
                        Profile updated.
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
