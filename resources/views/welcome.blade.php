<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'INTSEC') }}</title>
        <x-app-assets />
    </head>
    <body class="bg-zinc-950 text-zinc-100">
        <main class="min-h-screen">
            <section class="mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <x-intsec-brand />
                    <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Application intrusion monitoring</p>
                    <h1 class="mt-5 text-4xl font-semibold tracking-normal text-white md:text-6xl">Security operations, in one place.</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-300">
                        Integrated authentication monitoring, account visibility, and incident response foundations for Laravel applications.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-cyan-400 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Open dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md bg-cyan-400 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Sign in</a>
                        @endauth
                    </div>
                </div>
                <div class="mt-14 grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/70 p-5">
                        <h2 class="font-semibold text-white">Authentication logs</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-400">Successful, failed, and logout activity is persisted with request context.</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/70 p-5">
                        <h2 class="font-semibold text-white">Role-aware access</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-400">Administrator routes are protected server-side and unavailable to standard users.</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/70 p-5">
                        <h2 class="font-semibold text-white">User workspace</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-400">Users can view recent login activity and manage basic account settings.</p>
                    </div>
                </div>
            </section>
        </main>

    </body>
</html>
