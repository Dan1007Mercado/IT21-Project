<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Security\MfaAttemptLimiter;
use App\Services\Security\MfaSecurityService;
use App\Services\Security\MfaSessionService;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MfaManagementController extends Controller
{
    public function regenerateRecoveryCodes(
        Request $request,
        TotpService $totp,
        RecoveryCodeService $recovery,
        MfaSessionService $mfaSession,
        MfaAttemptLimiter $limiter,
        MfaSecurityService $security,
    ): RedirectResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'code' => ['required', 'digits:6'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->guardAttempt($request, $user, $limiter, 'recovery-regenerate');

        $codes = DB::transaction(function () use ($totp, $user, $validated, $recovery): array {
            if (! $totp->consume($user, $validated['code'])) {
                throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
            }

            return $recovery->regenerate($user);
        });
        $mfaSession->storeRecoveryBundle($request, $codes, 'management');
        $limiter->clear($request, $user, 'recovery-regenerate');
        $security->record($request, $user, '2FA_RECOVERY_REGENERATED', 'Emergency recovery codes regenerated');
        $security->notify($user, 'Your emergency recovery codes were regenerated. All previous codes are now invalid.');

        return redirect()->route('mfa.recovery-codes.show');
    }

    public function startReplacement(
        Request $request,
        TotpService $totp,
        MfaAttemptLimiter $limiter,
        MfaSecurityService $security,
    ): RedirectResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'code' => ['required', 'digits:6'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->guardAttempt($request, $user, $limiter, 'authenticator-replace');

        DB::transaction(function () use ($totp, $user, $validated): void {
            if (! $totp->consume($user, $validated['code'])) {
                throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
            }

            $user->forceFill(['pending_totp_secret' => $totp->generateSecret()])->save();
        });
        $security->record($request, $user, '2FA_QR_GENERATED', 'Replacement authenticator QR generated');
        $limiter->clear($request, $user, 'authenticator-replace');

        return redirect()->route('profile.mfa.replace.show');
    }

    public function showReplacement(Request $request, TotpService $totp): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! filled($user->pending_totp_secret)) {
            return redirect()->route('profile.edit');
        }

        $secret = (string) $user->pending_totp_secret;

        return view('profile.replace-authenticator', [
            'qrDataUri' => $totp->qrDataUri($totp->provisioningUri($user, $secret)),
            'manualSecret' => $totp->formattedSecret($secret),
            'issuer' => config('mfa.issuer', 'INTSEC'),
            'account' => $user->email,
        ]);
    }

    public function confirmReplacement(
        Request $request,
        TotpService $totp,
        RecoveryCodeService $recovery,
        MfaSessionService $mfaSession,
        MfaAttemptLimiter $limiter,
        MfaSecurityService $security,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();
        $this->guardAttempt($request, $user, $limiter, 'authenticator-replace-confirm');
        $secret = (string) $user->pending_totp_secret;
        $timestep = $secret === '' ? null : $totp->verify($secret, $validated['code']);

        if ($timestep === null) {
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
        }

        $codes = DB::transaction(function () use ($user, $secret, $timestep, $recovery): array {
            $user->forceFill([
                'totp_secret' => $secret,
                'pending_totp_secret' => null,
                'totp_confirmed_at' => now(),
                'mfa_enabled_at' => now(),
                'last_totp_timestep' => $timestep,
            ])->save();

            return $recovery->regenerate($user);
        });

        $mfaSession->storeRecoveryBundle($request, $codes, 'management');
        $limiter->clear($request, $user, 'authenticator-replace-confirm');
        $security->record($request, $user, '2FA_AUTHENTICATOR_REPLACED', 'Authenticator app replaced');
        $security->notify($user, 'Your authenticator app was replaced and new emergency recovery codes were generated.');

        return redirect()->route('mfa.recovery-codes.show');
    }

    private function guardAttempt(Request $request, User $user, MfaAttemptLimiter $limiter, string $action): void
    {
        if ($limiter->tooMany($request, $user, $action)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, $action);
    }
}
