<?php

namespace App\Http\Controllers;

use App\Mail\SecurityNotificationMail;
use App\Models\User;
use App\Services\Security\MfaSecurityService;
use App\Services\Security\MfaSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request, MfaSecurityService $security): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'recoveryCodesRemaining' => $request->user()->recoveryCodes()->whereNull('consumed_at')->count(),
            'maskedEmail' => $security->maskEmail($request->user()->email),
        ]);
    }

    public function update(Request $request, MfaSessionService $mfaSession): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['nullable', 'current_password:web'],
        ]);

        $emailChanged = strcasecmp($user->email, $validated['email']) !== 0;

        if ($emailChanged && (blank($validated['current_password'] ?? null) || ! $mfaSession->isRecentlyVerified($request, $user))) {
            throw ValidationException::withMessages([
                'current_password' => 'Changing your email requires your current password and recent MFA verification.',
            ]);
        }

        $oldEmail = $user->email;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            try {
                Mail::to($oldEmail)->send(new SecurityNotificationMail('The email address on your INTSEC account was changed.'));
            } catch (Throwable $exception) {
                Log::warning('An email-change security notification could not be delivered.', [
                    'user_id' => $user->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request, MfaSessionService $mfaSession): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $mfaSession->isRecentlyVerified($request, $user)) {
            throw ValidationException::withMessages([
                'current_password' => 'Recent MFA verification is required to change your password.',
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return back()->with('status', 'password-updated');
    }
}
