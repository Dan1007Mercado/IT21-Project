<x-layouts.auth title="Verify email - INTSEC">
    <section class="auth-flow auth-flow--medium">
        <x-intsec-brand />
        <p class="mt-8 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">Mandatory security setup</p>
        <h1 class="mt-2 text-2xl font-semibold text-white">Verify your email</h1>
        <p class="mt-2 text-sm text-zinc-400">We will send a single-use code to {{ $maskedEmail }} before registering your authenticator.</p>

        @if (session('status') === 'verification-code-sent')
            <p class="mt-5 rounded-md border border-emerald-700 bg-emerald-950/50 p-3 text-sm text-emerald-200" role="status">Verification code sent.</p>
        @endif
        @error('email_otp')<p class="mt-5 rounded-md border border-red-700 bg-red-950/50 p-3 text-sm text-red-200" role="alert">{{ $message }}</p>@enderror

        <form method="POST" action="{{ route('mfa.enrollment.email.send') }}" class="mt-6">
            @csrf
            <button class="w-full rounded-md border border-cyan-500 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-950">Send verification code</button>
        </form>

        <form method="POST" action="{{ route('mfa.enrollment.email.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium text-zinc-200">Verification code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-3 text-center text-xl tracking-[0.4em] text-white focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
            </div>
            <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Verify email and continue</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button class="text-sm text-zinc-400 hover:text-white">Sign out</button></form>
    </section>
</x-layouts.auth>
