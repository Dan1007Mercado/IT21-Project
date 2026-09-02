<x-layouts.app title="Profile - INTSEC">
    <h1 class="text-3xl font-semibold text-white">Profile</h1>
    <p class="mt-2 text-sm text-zinc-400">Manage your account details and password.</p>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-8 max-w-2xl rounded-lg border border-zinc-800 bg-zinc-900 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-zinc-200">Name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('name')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-zinc-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('email')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="password" class="block text-sm font-medium text-zinc-200">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                    @error('password')
                        <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-zinc-200">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                </div>
            </div>
        </div>

        <button type="submit" class="mt-6 rounded-md bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Save changes</button>
    </form>
</x-layouts.app>
