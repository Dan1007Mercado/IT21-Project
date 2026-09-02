<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sign in - {{ config('app.name', 'INTSEC') }}</title>
        <x-app-assets />
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-12">
            <section class="w-full max-w-md rounded-lg border border-zinc-800 bg-zinc-900 p-8 shadow-2xl">
                <x-intsec-brand />
                <h1 class="mt-8 text-2xl font-semibold text-white">Sign in</h1>
                <p class="mt-2 text-sm text-zinc-400">Use your INTSEC account credentials.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-zinc-200">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('email')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-zinc-200">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                        @error('password')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input name="remember" type="checkbox" value="1" class="rounded border-zinc-700 bg-zinc-950 text-cyan-400 focus:ring-cyan-400">
                        Remember this device
                    </label>

                    <button type="submit" class="w-full rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">
                        Sign in
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
