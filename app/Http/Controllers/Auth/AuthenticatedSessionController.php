<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\User;
use App\Services\Security\AuthActivityLogger;
use App\Services\Security\LoginProtectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuthActivityLogger $logger, LoginProtectionService $loginProtectionService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (BlockedIp::isBlocked((string) $request->ip())) {
            $logger->record($request, 'login', 'failed', null, $credentials['email'], 'ip_blocked');

            throw ValidationException::withMessages([
                'email' => 'This IP address is temporarily blocked due to repeated failed login attempts.',
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user && ! $user->is_active) {
            $logger->record($request, 'login', 'failed', $user, $credentials['email'], 'account_disabled');

            throw ValidationException::withMessages([
                'email' => 'This account is disabled.',
            ]);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $logger->record($request, 'login', 'failed', $user, $credentials['email'], 'invalid_credentials');
            $loginProtectionService->recordFailedAttempt($request, $user, $credentials['email']);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();

        $logger->record($request, 'login', 'successful', $authenticatedUser, $credentials['email']);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuthActivityLogger $logger): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            $logger->record($request, 'logout', 'successful', $user, $user->email);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
