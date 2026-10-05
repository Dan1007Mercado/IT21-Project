@props([
    'compact' => false,
    'class' => 'inline-flex items-center gap-3',
])

<a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="{{ $class }}" aria-label="INTSEC home">
    <img
        src="{{ Vite::asset('resources/assets/INTSEC.png') }}"
        alt="INTSEC"
        class="{{ $compact ? 'h-9 w-9' : 'h-11 w-11' }} rounded-md object-contain object-center"
    >
    <span class="sidebar-brand-copy">
        <span class="block text-base font-semibold text-white">INTSEC</span>
        @unless ($compact)
            <span class="mt-0.5 block text-xs text-zinc-500">Intrusion monitoring</span>
        @endunless
    </span>
</a>
