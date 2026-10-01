@props([
    'title' => config('app.name', 'INTSEC'),
    'wide' => false,
    'flush' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <x-app-assets />
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="min-h-screen lg:pl-72">
        <x-navigation.sidebar />
        <x-navigation.mobile-navigation />

        <main @class([
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
    {{ $scripts ?? '' }}
</body>
</html>
