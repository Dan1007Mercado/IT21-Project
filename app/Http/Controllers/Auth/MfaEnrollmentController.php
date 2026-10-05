<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MfaOtpChallenge;
use App\Models\User;
use App\Services\Security\EmailOtpService;
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
use Throwable;

final class MfaEnrollmentController extends Controller
{
    public function show(
        Request $request,
        MfaSessionService $mfaSession,
        RecoveryCodeService $recovery,
    ): View|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        if ($mfaSession->recoveryBundle($request) !== null) {
            return redirect()->route('mfa.recovery-codes.show');
        }

        if ($user->mfa_enabled_at !== null && $user->recovery_codes_confirmed_at === null) {
            $mfaSession->storeRecoveryBundle($request, $recovery->regenerate($user), 'enrollment');

            return redirect()->route('mfa.recovery-codes.show');
        }

        if ($user->hasMfaConfigured()) {
            return redirect()->route('mfa.challenge.show');
        }

        if ($user->email_verified_at !== null) {
            return redirect()->route('mfa.enrollment.authenticator');
        }

        return view('auth.mfa.enroll-email', [
            'maskedEmail' => app(MfaSecurityService::class)->maskEmail($user->email),
        ]);
    }

    public function sendEmail(
        Request $request,
        EmailOtpService $otp,
        MfaAttemptLimiter $limiter,
        MfaSecurityService $security,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        if ($limiter->tooMany($request, $user, 'enrollment-email-send', 3)) {
            return back()->withErrors(['email_otp' => 'Too many requests. Please try again later.']);
        }

        $limiter->hit($request, $user, 'enrollment-email-send', 300);

        try {
            $otp->send($user, MfaOtpChallenge::PURPOSE_EMAIL_VERIFICATION);
        } catch (Throwable) {
            return back()->withErrors(['email_otp' => 'The verification email could not be sent. Please try again later.']);
        }

        $security->record($request, $user, '2FA_EMAIL_VERIFICATION_SENT', 'MFA enrollment email verification sent');

        return back()->with('status', 'verification-code-sent');
    }

    public function verifyEmail(
        Request $request,
        EmailOtpService $otp,
        MfaAttemptLimiter $limiter,
        MfaSecurityService $security,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();

        if ($limiter->tooMany($request, $user, 'enrollment-email-verify')) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, 'enrollment-email-verify');

        $verified = DB::transaction(function () use ($otp, $user, $validated): bool {
            if (! $otp->verify($user, MfaOtpChallenge::PURPOSE_EMAIL_VERIFICATION, $validated['code'])) {
                return false;
            }

            $user->forceFill(['email_verified_at' => now()])->save();

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
        }

        $limiter->clear($request, $user, 'enrollment-email-verify');
        $security->record($request, $user, '2FA_EMAIL_VERIFIED', 'Email verified for MFA enrollment');

        return redirect()->route('mfa.enrollment.authenticator');
    }

    public function authenticator(
        Request $request,
        TotpService $totp,
        MfaSecurityService $security,
    ): View|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at === null) {
            return redirect()->route('mfa.enrollment.show');
        }

        if ($user->hasMfaConfigured()) {
            return redirect()->route('mfa.challenge.show');
        }

        if (! filled($user->pending_totp_secret)) {
            $user->forceFill(['pending_totp_secret' => $totp->generateSecret()])->save();
            $security->record($request, $user, '2FA_QR_GENERATED', 'Authenticator enrollment QR generated');
        }

        $secret = (string) $user->fresh()->pending_totp_secret;
        $uri = $totp->provisioningUri($user, $secret);

        return view('auth.mfa.enroll-authenticator', [
            'qrDataUri' => $totp->qrDataUri($uri),
            'manualSecret' => $totp->formattedSecret($secret),
            'issuer' => config('mfa.issuer', 'INTSEC'),
            'account' => $user->email,
        ]);
    }

    public function confirmAuthenticator(
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

        if ($limiter->tooMany($request, $user, 'enrollment-totp')) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, 'enrollment-totp');
        $secret = (string) $user->pending_totp_secret;
        $timestep = $secret === '' ? null : $totp->verify($secret, $validated['code']);

        if ($timestep === null) {
            $security->record($request, $user, '2FA_TOTP_FAILED', 'Authenticator enrollment verification failed');
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

        $mfaSession->storeRecoveryBundle($request, $codes, 'enrollment');
        $limiter->clear($request, $user, 'enrollment-totp');
        $security->record($request, $user, '2FA_TOTP_ENABLED', 'Authenticator app registered');
        $security->record($request, $user, '2FA_ENABLED', 'Multi-factor authentication enabled');
        $security->notify($user, 'Multi-factor authentication was enabled on your account.');

        return redirect()->route('mfa.recovery-codes.show');
    }
}
