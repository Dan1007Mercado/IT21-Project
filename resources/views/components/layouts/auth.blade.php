@props(['title' => 'Sign in - INTSEC', 'immersive' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#060e18">
    <title>{{ $title }}</title>
    @if($immersive)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Orbitron:wght@600;700;800&display=swap" rel="stylesheet">
    @endif
    <x-app-assets />
</head>
<body @class(['min-h-screen bg-zinc-950 text-zinc-100 antialiased', 'login-page' => $immersive, 'auth-shell-page' => ! $immersive])>
    @if($immersive)
        {{ $slot }}
    @else
        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:py-12">{{ $slot }}</main>
    @endif
</body>
</html>
