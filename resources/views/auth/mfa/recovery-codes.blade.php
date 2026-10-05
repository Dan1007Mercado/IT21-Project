<x-layouts.auth title="Save recovery codes - INTSEC">
    <section class="w-full max-w-2xl rounded-lg border border-zinc-800 bg-zinc-900 p-8 shadow-2xl">
        <x-intsec-brand />
        <h1 class="mt-8 text-2xl font-semibold text-white">Save your recovery codes</h1>
        <p class="mt-2 text-sm text-zinc-400">These codes are shown once. Store them securely and offline. Each code can only be used once.</p>
        <div id="recovery-codes" class="mt-6 grid gap-2 rounded-lg border border-zinc-700 bg-zinc-950 p-5 font-mono text-sm text-cyan-200 sm:grid-cols-2">
            @foreach ($codes as $code)<span>{{ $code }}</span>@endforeach
        </div>
        <div class="mt-5 flex flex-wrap gap-3">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('recovery-codes').innerText)" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Copy codes</button>
            <a href="{{ route('mfa.recovery-codes.download') }}" class="rounded-md border border-cyan-500 px-4 py-2 text-sm font-semibold text-cyan-200">Download emergency recovery file</a>
            <button type="button" onclick="window.print()" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Print</button>
        </div>
        <form method="POST" action="{{ route('mfa.recovery-codes.finish') }}" class="mt-7 space-y-4">
            @csrf
            <label class="flex items-start gap-3 text-sm text-zinc-200"><input name="acknowledged" type="checkbox" value="1" required class="mt-0.5 rounded border-zinc-600 bg-zinc-950 text-cyan-400"><span>I have securely saved my recovery codes and understand they cannot be shown again.</span></label>
            @error('acknowledged')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
            <button class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950">{{ $context === 'enrollment' ? 'Finish security setup' : 'Return to profile & security' }}</button>
        </form>
    </section>
</x-layouts.auth>
