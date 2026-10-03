<x-layouts.app title="Edit User - INTSEC">
    <a href="{{ route('users.show', $managedUser) }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; Back to user</a>
    <h1 class="mt-4 text-3xl font-semibold text-white">Edit {{ $managedUser->name }}</h1>
    <form method="POST" action="{{ route('users.update', $managedUser) }}" class="mt-6 rounded-lg border border-zinc-800 bg-zinc-900 p-6">@csrf @method('PUT')
        @include('users._form')
        <div class="mt-6 flex justify-end"><button class="rounded-md bg-cyan-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-cyan-400">Save changes</button></div>
    </form>
</x-layouts.app>
