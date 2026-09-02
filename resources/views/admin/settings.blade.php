<x-layouts.app title="System Settings - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">System settings</h1>
        </div>
        <a href="{{ route('admin.audit-logs') }}" class="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-300 hover:border-cyan-500/40">View audit logs</a>
    </div>

    @if (session('status') === 'settings-updated')
        <div class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">
            Security threshold settings updated successfully.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.store') }}" class="mt-8 space-y-8">
        @csrf

        <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-lg font-semibold text-white">Authentication protection</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-zinc-200">Maximum failed attempts</span>
                    <input type="number" min="1" name="max_login_attempts" value="{{ old('max_login_attempts', $settings['max_login_attempts']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">Failed attempts allowed before temporary blocking activates.</small>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-zinc-200">Attempt window (minutes)</span>
                    <input type="number" min="1" name="login_attempt_window_minutes" value="{{ old('login_attempt_window_minutes', $settings['login_attempt_window_minutes']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">The rolling time window used to evaluate excess failed logins.</small>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-zinc-200">Temporary block duration (minutes)</span>
                    <input type="number" min="1" name="login_block_duration_minutes" value="{{ old('login_block_duration_minutes', $settings['login_block_duration_minutes']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">Duration of the temporary authentication block for repeated failures.</small>
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-lg font-semibold text-white">Detection thresholds</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-zinc-200">Failed-login warning threshold</span>
                    <input type="number" min="1" name="failed_login_warning_threshold" value="{{ old('failed_login_warning_threshold', $settings['failed_login_warning_threshold']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">When a source crosses this threshold, the event is treated as a warning.</small>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-zinc-200">Repeated authentication threshold</span>
                    <input type="number" min="1" name="repeated_authentication_threshold" value="{{ old('repeated_authentication_threshold', $settings['repeated_authentication_threshold']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">The trigger level for repeated suspicious authentication behavior.</small>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-zinc-200">Repeated-IP activity threshold</span>
                    <input type="number" min="1" name="repeated_ip_activity_threshold" value="{{ old('repeated_ip_activity_threshold', $settings['repeated_ip_activity_threshold']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">The repeated source-IP activity threshold used in the monitoring workflow.</small>
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-lg font-semibold text-white">IP controls</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-1">
                <label class="block">
                    <span class="text-sm font-medium text-zinc-200">Default block duration (minutes)</span>
                    <input type="number" min="1" name="default_ip_block_duration_minutes" value="{{ old('default_ip_block_duration_minutes', $settings['default_ip_block_duration_minutes']) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" />
                    <small class="mt-1 block text-xs text-zinc-400">Fallback duration for administrative IP blocks.</small>
                </label>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-md border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-100">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-cyan-500 px-4 py-2 text-sm font-medium text-slate-950 hover:bg-cyan-400">Save system settings</button>
        </div>
    </form>
</x-layouts.app>
