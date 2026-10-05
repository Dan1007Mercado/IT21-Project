@props(['label', 'value', 'tone' => 'cyan', 'icon' => null])
@php($tones = ['cyan' => 'text-cyan-300', 'emerald' => 'text-emerald-300', 'amber' => 'text-amber-300', 'red' => 'text-red-300', 'zinc' => 'text-zinc-100'])
<div {{ $attributes->class('relative rounded-lg border border-zinc-800 bg-zinc-900/70 p-5') }}>
    @if ($icon)
        <span class="absolute right-4 top-4 rounded-lg border border-white/10 bg-white/5 p-2 {{ $tones[$tone] ?? $tones['cyan'] }}" aria-hidden="true">
            @switch($icon)
                @case('activity')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.2-7 4.1 14L16 12h5" /></svg>
                    @break
                @case('shield')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" /></svg>
                    @break
                @case('alert')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 17H3L12 3Z" /><path stroke-linecap="round" d="M12 9v4" /><path stroke-linecap="round" d="M12 16h.01" /></svg>
                    @break
                @case('bell')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" /></svg>
                    @break
            @endswitch
        </span>
    @endif
    <p class="metric-card-label text-xs uppercase tracking-[0.18em] text-zinc-500">{{ $label }}</p><p class="metric-card-value mt-2 text-3xl font-semibold {{ $tones[$tone] ?? $tones['cyan'] }}">{{ $value }}</p>{{ $slot }}
</div>
