@php $editing = isset($managedUser); @endphp
<div class="ops-form-grid">
    <label class="ops-field ops-field--wide"><span class="ops-label">Name <span class="ops-required">required</span></span>
        <input name="name" value="{{ old('name', $managedUser->name ?? '') }}" required maxlength="255">
        @error('name')<span class="ops-error">{{ $message }}</span>@enderror
    </label>
    <label class="ops-field ops-field--wide"><span class="ops-label">Email <span class="ops-required">required</span></span>
        <input type="email" name="email" value="{{ old('email', $managedUser->email ?? '') }}" required>
        @error('email')<span class="ops-error">{{ $message }}</span>@enderror
    </label>
    <label class="ops-field"><span class="ops-label">Role <span class="ops-required">required</span></span>
        <select name="role" required>
            <option value="standard_user" @selected(old('role', $managedUser->role ?? 'standard_user') === 'standard_user')>Standard user</option>
            <option value="administrator" @selected(old('role', $managedUser->role ?? '') === 'administrator')>Administrator</option>
        </select>
        @error('role')<span class="ops-error">{{ $message }}</span>@enderror
    </label>
    <label class="ops-field"><span class="ops-label">Account status <span class="ops-required">required</span></span>
        <select name="is_active" required>
            <option value="1" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '1')>Active</option>
            <option value="0" @selected((string) old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) === '0')>Inactive</option>
        </select>
        @error('is_active')<span class="ops-error">{{ $message }}</span>@enderror
    </label>
    <label class="ops-field ops-field--wide"><span class="ops-label">Password {{ $editing ? '(leave blank to keep current)' : '' }}</span>
        <input type="password" name="password" @required(! $editing) autocomplete="new-password">
        @error('password')<span class="ops-error">{{ $message }}</span>@enderror
    </label>
    <label class="ops-field ops-field--wide"><span class="ops-label">Confirm password</span>
        <input type="password" name="password_confirmation" @required(! $editing) autocomplete="new-password">
    </label>
</div>
