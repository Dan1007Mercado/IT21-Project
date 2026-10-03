<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%'));
        }
        if (in_array($request->input('role'), ['administrator', 'standard_user'], true)) {
            $query->where('role', $request->input('role'));
        }
        if (in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        return view('users.index', [
            'users' => $query->orderBy('name')->simplePaginate(10)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $user = User::query()->create($validated);
        $this->audit($request, 'user_created', $user, null, $this->auditValues($user));

        return redirect()->route('users.show', $user)->with('status', 'user-created');
    }

    public function show(User $user): View
    {
        return view('users.show', ['managedUser' => $user]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['managedUser' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($request, $user, $validated): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $wouldLoseAdmin = $locked->isAdministrator() && $locked->is_active
                && ($validated['role'] !== 'administrator' || ! $validated['is_active']);

            if ($locked->is($request->user()) && $wouldLoseAdmin) {
                throw ValidationException::withMessages(['role' => 'You cannot deactivate or remove your own administrator access.']);
            }
            if ($wouldLoseAdmin && User::query()->where('role', 'administrator')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active administrator must remain.']);
            }

            $before = $this->auditValues($locked);
            $locked->fill($validated)->save();
            $this->audit($request, 'user_updated', $locked, $before, $this->auditValues($locked));
        });

        return redirect()->route('users.show', $user)->with('status', 'user-updated');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->is($request->user())) {
                throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
            }
            if ($locked->isAdministrator() && $locked->is_active
                && User::query()->where('role', 'administrator')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'The last active administrator cannot be deleted.']);
            }

            $before = $this->auditValues($locked);
            $id = $locked->id;
            $name = $locked->name;
            $locked->delete();
            AuditLog::record('user_deleted', 'user', $name, $id, $before, null, 'A user account was deleted.', $request->ip(), $request->user());
        });

        return redirect()->route('users.index')->with('status', 'user-deleted');
    }

    private function audit(Request $request, string $action, User $user, ?array $before, array $after): void
    {
        AuditLog::record($action, 'user', $user->name, $user->id, $before, $after, 'A user account was changed.', $request->ip(), $request->user());
    }

    private function auditValues(User $user): array
    {
        return Arr::only($user->attributesToArray(), ['name', 'email', 'role', 'is_active']);
    }
}
