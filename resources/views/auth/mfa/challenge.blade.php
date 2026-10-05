<x-layouts.auth title="Two-factor authentication - INTSEC">
    <section class="w-full max-w-md rounded-lg border border-zinc-800 bg-zinc-900 p-8 shadow-2xl">
        <x-intsec-brand />
        <h1 class="mt-8 text-2xl font-semibold text-white">Two-factor authentication</h1>
        <p class="mt-2 text-sm text-zinc-400">Enter the current code from your authenticator app.</p>
        @error('email_otp')<p class="mt-5 rounded-md border border-amber-700 bg-amber-950/50 p-3 text-sm text-amber-200">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('mfa.challenge.totp.verify') }}" class="mt-7 space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-zinc-200">Authenticator code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-3 text-center text-xl tracking-[0.4em] text-white focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
            </div>
            <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Verify</button>
        </form>
        <div class="mt-7 border-t border-zinc-800 pt-5">
            <p class="text-sm text-zinc-400">Can't access your authenticator?</p>
            <div class="mt-3 flex flex-wrap gap-3"><a href="{{ route('mfa.challenge.email') }}" class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:border-cyan-500">Send email code</a><a href="{{ route('mfa.challenge.recovery') }}" class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:border-cyan-500">Use emergency recovery</a></div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button class="text-sm text-zinc-500 hover:text-white">Sign out</button></form>
    </section>
</x-layouts.auth>
