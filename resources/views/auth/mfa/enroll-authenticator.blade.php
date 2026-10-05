<x-layouts.auth title="Set up authenticator - INTSEC">
    <section class="auth-flow">
        <x-intsec-brand />
        <p class="mt-8 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">Mandatory security setup</p>
        <h1 class="mt-2 text-2xl font-semibold text-white">Set up authenticator app</h1>
        <p class="mt-2 text-sm text-zinc-400">Use Google Authenticator, Microsoft Authenticator, 1Password, Authy, or another RFC 6238-compatible app.</p>

        <div class="mt-8 grid gap-8 md:grid-cols-[320px_1fr]">
            <div>
                <p class="text-sm font-semibold text-zinc-200">1. Scan this QR code</p>
                <div class="mt-3 inline-block rounded-xl bg-white p-3">
                    <img src="{{ $qrDataUri }}" width="300" height="300" alt="Authenticator setup QR code" class="h-auto w-full max-w-[300px]">
                </div>
            </div>
            <div class="space-y-6">
                <div>
                    <p class="text-sm font-semibold text-zinc-200">Can't scan the QR code?</p>
                    <p class="mt-2 text-xs uppercase tracking-wide text-zinc-500">Manual setup key</p>
                    <code class="mt-1 block break-all rounded-md border border-zinc-700 bg-zinc-950 p-3 text-sm text-cyan-200">{{ $manualSecret }}</code>
                    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm"><dt class="text-zinc-500">Account</dt><dd class="break-all text-zinc-200">{{ $account }}</dd><dt class="text-zinc-500">Issuer</dt><dd class="text-zinc-200">{{ $issuer }}</dd></dl>
                </div>

                <form method="POST" action="{{ route('mfa.enrollment.authenticator.confirm') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="code" class="block text-sm font-semibold text-zinc-200">2. Enter the current 6-digit code</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-3 text-center text-xl tracking-[0.4em] text-white focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                    </div>
                    <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Verify &amp; enable authenticator</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.auth>
