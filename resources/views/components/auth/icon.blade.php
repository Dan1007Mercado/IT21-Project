@props(['name'])
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/> @break
        @case('lock') <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/> @break
        @case('eye') <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/> @break
        @case('shield') <path d="m12 2 9 4-1 8c-.6 4-4.8 6.5-8 8-3.2-1.5-7.4-4-8-8L3 6l9-4Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('chart') <rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/> @break
        @case('network') <circle cx="5" cy="5" r="3"/><circle cx="19" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="m7 7 10 10M7 17 17 7"/> @break
        @case('gear') <path d="m9 3 .5-1h5l.5 3 2 1 3-1 2 4-2 2v2l2 2-2 4-3-1-2 1-.5 3h-5L9 20l-2-1-3 1-2-4 2-2v-2l-2-2 2-4 3 1 2-1V3Z"/><circle cx="12" cy="12" r="3"/> @break
        @case('arrow') <path d="M4 12h16m-6-6 6 6-6 6"/> @break
    @endswitch
</svg>
