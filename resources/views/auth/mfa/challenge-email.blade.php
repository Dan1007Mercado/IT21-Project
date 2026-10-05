<x-layouts.auth title="Email verification - INTSEC">
    <section class="auth-flow auth-flow--compact">
        <x-intsec-brand />
        <h1 class="mt-8 text-2xl font-semibold text-white">Email verification</h1>
        <p class="mt-2 text-sm text-zinc-400">Send a fallback verification code to {{ $maskedEmail }}.</p>
        @if (session('status') === 'verification-code-sent')<p class="mt-5 rounded-md border border-emerald-700 bg-emerald-950/50 p-3 text-sm text-emerald-200">Verification code sent. It expires in {{ (int) ceil(config('mfa.email_otp_ttl', 300) / 60) }} minutes.</p>@endif
        @error('email_otp')<p class="mt-5 text-sm text-red-300">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('mfa.challenge.email.send') }}" class="mt-6">@csrf<button class="w-full rounded-md border border-cyan-500 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-950">Send email code</button><p class="mt-2 text-xs text-zinc-500">Resend is available after {{ config('mfa.email_otp_resend_cooldown', 60) }} seconds.</p></form>
        <form method="POST" action="{{ route('mfa.challenge.email.verify') }}" class="mt-6 space-y-4">
            @csrf
            <label for="code" class="block text-sm font-medium text-zinc-200">Verification code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-3 text-center text-xl tracking-[0.4em] text-white focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
            @error('code')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
            <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">Verify</button>
        </form>
        <a href="{{ route('mfa.challenge.show') }}" class="mt-6 block text-center text-sm text-zinc-400 hover:text-white">Back to authenticator</a>
    </section>
</x-layouts.auth>
