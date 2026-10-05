@props(['name'])

<svg {{ $attributes->class('navigation-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard') <path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" /> @break
        @case('overview') <path d="M4 19V9m5 10V5m6 14v-7m5 7V3" /> @break
        @case('login') <path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3m-4-4h11m-3-3 3 3-3 3" /> @break
        @case('map') <path d="m3 6 5-3 8 3 5-3v15l-5 3-8-3-5 3V6Zm5-3v15m8-12v15" /> @break
        @case('requests') <path d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01" /> @break
        @case('frequency') <path d="M3 12h4l2-7 4 14 2-7h6" /> @break
        @case('spikes') <path d="m3 18 5-6 4 3 5-9 4 4M17 6h4v4" /> @break
        @case('events') <path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Zm0 5v4m0 4h.01" /> @break
        @case('audit') <path d="M9 4h6m-7 0H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h7m5-12V6a2 2 0 0 0-2-2h-1m-7 6h5m-5 4h3m7 1v6m-3-3h6" /> @break
        @case('alerts') <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" /> @break
        @case('incidents') <path d="m12 3 9 17H3L12 3Zm0 6v4m0 3h.01" /> @break
        @case('ip') <path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Zm-3 9 2 2 4-4" /> @break
        @case('rules') <path d="M4 6h10m4 0h2M4 12h2m4 0h10M4 18h8m4 0h4M14 4v4M6 10v4m6 2v4" /> @break
        @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3v6m3-3h-6" /> @break
        @case('settings') <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 8.94 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.57 15 1.7 1.7 0 0 0 3 14H3v-4h.09A1.7 1.7 0 0 0 4.6 8.94a1.7 1.7 0 0 0-.34-1.88L4.2 7l2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.57 1.7 1.7 0 0 0 10 3h4v.09A1.7 1.7 0 0 0 15.06 4.6a1.7 1.7 0 0 0 1.88-.34L17 4.2 19.83 7l-.06.06A1.7 1.7 0 0 0 19.43 9 1.7 1.7 0 0 0 21 10h.09v4H21a1.7 1.7 0 0 0-1.6 1Z" /> @break
        @case('profile') <circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /> @break
        @case('logout') <path d="M10 17l5-5-5-5m5 5H3m12-9h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /> @break
        @case('monitor') <rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8m-4-4v4m-6-9h3l2-4 2 7 2-3h3" /> @break
        @case('operations') <path d="M12 3 5 6v5c0 4.6 2.9 8.3 7 10 4.1-1.7 7-5.4 7-10V6l-7-3Z" /> @break
        @case('system') <rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 9h18M8 4v5" /> @break
        @case('menu') <path d="M4 7h16M4 12h16M4 17h16" /> @break
        @case('chevron') <path d="m9 18 6-6-6-6" /> @break
        @case('collapse') <path d="m15 18-6-6 6-6" /> @break
    @endswitch
</svg>
