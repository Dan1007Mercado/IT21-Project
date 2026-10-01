@props(['title' => 'Sign in - INTSEC'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title><x-app-assets /></head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased"><main class="flex min-h-screen items-center justify-center px-4 py-12">{{ $slot }}</main></body>
</html>
