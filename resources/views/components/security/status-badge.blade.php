@props(['status'])
<span class="inline-flex rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs font-medium text-zinc-300">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
