@php($editing = isset($managedUser))
<div class="grid gap-6 lg:grid-cols-2">
    <label class="block text-sm text-zinc-300">Name
        <input name="name" value="{{ old('name', $managedUser->name ?? '') }}" required maxlength="255" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
        @error('name')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm text-zinc-300">Email
        <input type="email" name="email" value="{{ old('email', $managedUser->email ?? '') }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
        @error('email')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm text-zinc-300">Role
        <select name="role" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            <option value="standard_user" @selected(old('role', $managedUser->role ?? 'standard_user') === 'standard_user')>Standard user</option>
            <option value="administrator" @selected(old('role', $managedUser->role ?? '') === 'administrator')>Administrator</option>
        </select>
        @error('role')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm text-zinc-300">Account status
        <select name="is_active" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            <option value="1" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '1')>Active</option>
            <option value="0" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '0')>Inactive</option>
        </select>
        @error('is_active')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm text-zinc-300">Password {{ $editing ? '(leave blank to keep current)' : '' }}
        <input type="password" name="password" @required(! $editing) autocomplete="new-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
        @error('password')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm text-zinc-300">Confirm password
        <input type="password" name="password_confirmation" @required(! $editing) autocomplete="new-password" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
    </label>
</div>
