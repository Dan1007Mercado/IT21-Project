<x-layouts.auth title="Emergency recovery - INTSEC">
    <section class="auth-flow auth-flow--medium">
        <x-intsec-brand />
        <h1 class="mt-8 text-2xl font-semibold text-white">Emergency recovery</h1>
        <p class="mt-2 text-sm text-zinc-400">Use this only if you cannot access your authenticator or verified email. Your password and reCAPTCHA must already have been accepted.</p>
        <div class="mt-7 grid gap-7 md:grid-cols-2">
            <form method="POST" action="{{ route('mfa.challenge.recovery.code') }}" class="space-y-4">
                @csrf
                <label for="recovery_code" class="block text-sm font-semibold text-zinc-200">Enter recovery code</label>
                <input id="recovery_code" name="recovery_code" autocomplete="one-time-code" required class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2.5 font-mono uppercase text-white focus:border-cyan-400">
                @error('recovery_code')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
                <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">Verify recovery code</button>
            </form>
            <form method="POST" action="{{ route('mfa.challenge.recovery.file') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <label for="recovery_file" class="block text-sm font-semibold text-zinc-200">Upload emergency recovery file</label>
                <input id="recovery_file" name="recovery_file" type="file" accept=".txt,text/plain" required class="block w-full text-sm text-zinc-300 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-700 file:px-3 file:py-2 file:text-zinc-100">
                @error('recovery_file')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
                <button class="w-full rounded-md border border-cyan-500 px-4 py-2.5 text-sm font-semibold text-cyan-200">Verify recovery file</button>
            </form>
        </div>
        <a href="{{ route('mfa.challenge.show') }}" class="mt-7 block text-center text-sm text-zinc-400 hover:text-white">Back to authenticator</a>
    </section>
</x-layouts.auth>
