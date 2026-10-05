@props(['href', 'active' => false, 'short' => null, 'icon' => null])

@php
    $label = trim(strip_tags((string) $slot));
    $shortLabel = $short ?: collect(preg_split('/\s+/', $label))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp

<a href="{{ $href }}" title="{{ $label }}" @if($active) aria-current="page" @endif {{ $attributes->class([
    'block rounded-md px-3 py-2.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-cyan-400/60',
    'bg-cyan-400/10 text-cyan-200' => $active,
    'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! $active,
]) }}>
    @if ($icon)<span class="sidebar-nav-icon"><x-navigation.icon :name="$icon" /></span>@endif
    <span class="sidebar-nav-label">{{ $slot }}</span>
    <span class="sidebar-nav-short" aria-hidden="true">{{ $shortLabel }}</span>
</a>
