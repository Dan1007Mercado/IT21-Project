@props(['label', 'value', 'tone' => 'cyan'])
@php($tones = ['cyan' => 'text-cyan-300', 'emerald' => 'text-emerald-300', 'amber' => 'text-amber-300', 'red' => 'text-red-300', 'zinc' => 'text-zinc-100'])
<div {{ $attributes->class('rounded-lg border border-zinc-800 bg-zinc-900/70 p-5') }}><p class="text-xs uppercase tracking-[0.18em] text-zinc-500">{{ $label }}</p><p class="mt-2 text-3xl font-semibold {{ $tones[$tone] ?? $tones['cyan'] }}">{{ $value }}</p>{{ $slot }}</div>
