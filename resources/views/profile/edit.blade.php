<x-layouts.app title="Profile & Security - INTSEC">
    <div class="max-w-4xl">
        <h1 class="text-3xl font-semibold text-white">Profile &amp; Security</h1>
        <p class="mt-2 text-sm text-zinc-400">Manage account details, password, authenticator, and emergency recovery.</p>
        @if (session('status'))<p class="mt-6 rounded-md border border-emerald-700 bg-emerald-950/50 p-3 text-sm text-emerald-200" role="status">Security settings updated successfully.</p>@endif
        @if ($errors->any())<p class="mt-6 rounded-md border border-red-700 bg-red-950/50 p-3 text-sm text-red-200" role="alert">{{ $errors->first() }}</p>@endif

        <section class="mt-8 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-lg font-semibold text-white">Account information</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label for="name" class="block text-sm font-medium text-zinc-200">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-400">@error('name')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="block text-sm font-medium text-zinc-200">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-400">@error('email')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror</div>
                </div>
                <div><label for="account_current_password" class="block text-sm font-medium text-zinc-200">Current password <span class="text-zinc-500">(required to change email)</span></label><input id="account_current_password" name="current_password" type="password" autocomplete="current-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">@error('current_password')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror</div>
                <button class="rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">Save account changes</button>
            </form>
        </section>

        <section class="mt-6 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-lg font-semibold text-white">Password</h2>
            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-5 grid gap-5 md:grid-cols-2">
                @csrf @method('PUT')
                <div class="md:col-span-2"><label for="password_current" class="block text-sm font-medium text-zinc-200">Current password</label><input id="password_current" name="current_password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white"></div>
                <div><label for="password" class="block text-sm font-medium text-zinc-200">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">@error('password')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror</div>
                <div><label for="password_confirmation" class="block text-sm font-medium text-zinc-200">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white"></div>
                <div class="md:col-span-2"><button class="rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">Change password</button></div>
            </form>
        </section>

        <section class="mt-6 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold text-white">Two-factor authentication</h2><p class="mt-1 text-sm text-emerald-300">Enabled</p></div><span class="rounded-full border border-emerald-700 bg-emerald-950 px-3 py-1 text-xs text-emerald-200">Authenticator registered</span></div>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-zinc-500">Configured</dt><dd class="mt-1 text-zinc-200">{{ $user->mfa_enabled_at?->format('F j, Y') }}</dd></div><div><dt class="text-zinc-500">Email fallback</dt><dd class="mt-1 {{ $user->email_verified_at ? 'text-emerald-300' : 'text-amber-300' }}">{{ $maskedEmail }} &middot; {{ $user->email_verified_at ? 'Verified' : 'Verification required' }}</dd></div><div><dt class="text-zinc-500">Recovery codes remaining</dt><dd class="mt-1 text-zinc-200">{{ $recoveryCodesRemaining }}</dd></div></dl>
            <div class="mt-7 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('profile.mfa.replace.start') }}" class="rounded-md border border-zinc-800 p-4">@csrf<h3 class="font-semibold text-zinc-100">Replace authenticator</h3><p class="mt-1 text-xs text-zinc-500">Your current authenticator remains active until the replacement is confirmed.</p><input name="current_password" type="password" autocomplete="current-password" placeholder="Current password" required class="mt-4 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="Current authenticator code" required class="mt-3 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><button class="mt-4 rounded-md border border-cyan-500 px-3 py-2 text-sm font-semibold text-cyan-200">Replace authenticator</button></form>
                <form method="POST" action="{{ route('profile.mfa.recovery-codes') }}" class="rounded-md border border-zinc-800 p-4">@csrf<h3 class="font-semibold text-zinc-100">Emergency recovery</h3><p class="mt-1 text-xs text-zinc-500">Regeneration immediately invalidates every old code and file.</p><input name="current_password" type="password" autocomplete="current-password" placeholder="Current password" required class="mt-4 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="Current authenticator code" required class="mt-3 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><button class="mt-4 rounded-md border border-cyan-500 px-3 py-2 text-sm font-semibold text-cyan-200">Regenerate recovery codes</button></form>
            </div>
        </section>
    </div>
</x-layouts.app>
