<x-layouts.app title="Users - INTSEC" wide>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">System administration</p><h1 class="mt-2 text-3xl font-semibold text-white">Users</h1><p class="mt-2 text-sm text-zinc-400">Security operations workspace for managing access, roles, and account availability.</p></div>
        <a href="{{ route('users.create') }}" class="rounded-md bg-cyan-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-cyan-400">Create user</a>
    </div>

    @if (session('status'))<div class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">User operation completed.</div>@endif
    @error('user')<div class="mt-6 rounded-md border border-rose-700 bg-rose-950 px-4 py-3 text-sm text-rose-100">{{ $message }}</div>@enderror

    <form method="GET" class="mt-6 grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-4 md:grid-cols-[1fr_12rem_12rem_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="Search name or email" class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
        <select name="role" class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><option value="">All roles</option><option value="administrator" @selected(request('role') === 'administrator')>Administrators</option><option value="standard_user" @selected(request('role') === 'standard_user')>Standard users</option></select>
        <select name="status" class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
        <button class="rounded-md border border-cyan-500/40 px-4 py-2 text-sm text-cyan-200 hover:bg-cyan-950">Apply</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-zinc-800">@forelse($users as $managedUser)<tr>
            <td class="px-4 py-3"><a class="font-medium text-cyan-200 hover:text-cyan-100" href="{{ route('users.show', $managedUser) }}">{{ $managedUser->name }}</a><p class="text-xs text-zinc-500">{{ $managedUser->email }}</p></td>
            <td class="px-4 py-3 text-zinc-300">{{ $managedUser->isAdministrator() ? 'Administrator' : 'Standard user' }}</td>
            <td class="px-4 py-3"><span @class(['rounded-full px-2 py-1 text-xs', 'bg-emerald-950 text-emerald-300' => $managedUser->is_active, 'bg-zinc-800 text-zinc-400' => ! $managedUser->is_active])>{{ $managedUser->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td class="px-4 py-3 text-zinc-500">{{ $managedUser->created_at?->format('M j, Y') }}</td>
            <td class="px-4 py-3 text-right"><a class="text-cyan-300 hover:text-cyan-200" href="{{ route('users.edit', $managedUser) }}">Edit</a></td>
        </tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-zinc-400">No users match the current filters.</td></tr>@endforelse</tbody>
    </table></div></section>
    <div class="mt-6">{{ $users->links() }}</div>
</x-layouts.app>
