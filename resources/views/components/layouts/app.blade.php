@props([
    'title' => config('app.name', 'INTSEC'),
    'wide' => false,
    'flush' => false,
    'realtimeEntities' => '',
    'realtimeSource' => '',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#06111d">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <x-app-assets />
    @if (auth()->user()?->isAdministrator())
        <meta name="intsec-realtime-enabled" content="1">
    @endif
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased" data-realtime-entities="{{ $realtimeEntities }}" data-realtime-source="{{ $realtimeSource }}">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell min-h-screen lg:pl-64">
        <x-navigation.sidebar />
        <x-navigation.mobile-navigation />

        <main id="main-content" tabindex="-1" @class([
            'w-full',
            'px-4 py-6 sm:px-6 lg:px-8' => ! $flush,
            'mx-auto max-w-[1680px]' => $wide && ! $flush,
        ])>
            @if (session('status') === 'profile-updated')
                <div class="mx-4 mt-4 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100 sm:mx-6 lg:mx-8">Profile updated.</div>
            @endif
            {{ $slot }}
        </main>
    </div>
    <div id="intsec-realtime-indicator" class="fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-lg border border-cyan-500/40 bg-zinc-900 p-4 shadow-2xl" role="status" aria-live="polite">
        <p id="intsec-realtime-message" class="text-sm text-zinc-200">New security activity is available.</p>
        <button id="intsec-realtime-refresh" type="button" class="mt-2 text-sm font-semibold text-cyan-300 hover:text-cyan-200">Refresh current view</button>
    </div>
    {{ $scripts ?? '' }}
</body>
</html>
