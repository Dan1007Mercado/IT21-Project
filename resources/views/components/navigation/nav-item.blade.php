@props(['href', 'active' => false])

<a href="{{ $href }}" {{ $attributes->class([
    'block rounded-md px-3 py-2.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-cyan-400/60',
    'bg-cyan-400/10 text-cyan-200' => $active,
    'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! $active,
]) }}>{{ $slot }}</a>
