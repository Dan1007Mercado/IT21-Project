<x-layouts.app title="Replace authenticator - INTSEC">
    <div class="max-w-3xl">
        <a href="{{ route('profile.edit') }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; Profile &amp; Security</a>
        <h1 class="mt-4 text-3xl font-semibold text-white">Confirm replacement authenticator</h1>
        <p class="mt-2 text-sm text-zinc-400">Your existing authenticator remains active until the new code is verified.</p>
        <section class="mt-7 grid gap-8 rounded-lg border border-zinc-800 bg-zinc-900 p-6 md:grid-cols-[320px_1fr]">
            <div class="rounded-xl bg-white p-3"><img src="{{ $qrDataUri }}" width="300" height="300" alt="Replacement authenticator QR code" class="h-auto w-full"></div>
            <div><p class="text-sm text-zinc-300">Manual setup key</p><code class="mt-2 block break-all rounded-md border border-zinc-700 bg-zinc-950 p-3 text-sm text-cyan-200">{{ $manualSecret }}</code><p class="mt-3 text-sm text-zinc-400">Issuer: {{ $issuer }}<br>Account: {{ $account }}</p><form method="POST" action="{{ route('profile.mfa.replace.confirm') }}" class="mt-6">@csrf<label for="code" class="block text-sm font-medium text-zinc-200">New authenticator code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-3 text-center text-xl tracking-[0.4em] text-white">@error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror<button class="mt-4 w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">Verify replacement</button></form></div>
        </section>
    </div>
</x-layouts.app>
