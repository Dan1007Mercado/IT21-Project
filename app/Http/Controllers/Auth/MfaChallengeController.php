<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MfaOtpChallenge;
use App\Models\User;
use App\Services\Security\AuthActivityLogger;
use App\Services\Security\EmailOtpService;
use App\Services\Security\MfaAttemptLimiter;
use App\Services\Security\MfaSecurityService;
use App\Services\Security\MfaSessionService;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class MfaChallengeController extends Controller
{
    public function show(Request $request, MfaSessionService $mfaSession): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasMfaConfigured()) {
            return redirect()->route('mfa.enrollment.show');
        }

        if ($mfaSession->isVerified($request, $user)) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.mfa.challenge');
    }

    public function verifyTotp(
        Request $request,
        TotpService $totp,
        MfaAttemptLimiter $limiter,
        MfaSessionService $mfaSession,
        MfaSecurityService $security,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();

        if ($limiter->tooMany($request, $user, 'login-totp')) {
            $security->record($request, $user, '2FA_TOTP_RATE_LIMITED', 'Authenticator verification rate limited');
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, 'login-totp');

        if (! $totp->consume($user, $validated['code'])) {
            $security->record($request, $user, '2FA_TOTP_FAILED', 'Authenticator verification failed');
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
        }

        $limiter->clear($request, $user, 'login-totp');
        $mfaSession->markVerified($request, $user);
        $security->record($request, $user, '2FA_TOTP_SUCCESS', 'Authenticator verification succeeded');
        $logger->record($request, 'login', 'successful', $user, $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function email(Request $request, MfaSecurityService $security): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at === null) {
            return redirect()->route('mfa.challenge.show')
                ->withErrors(['email_otp' => 'Email fallback is unavailable until your email address is verified.']);
        }

        return view('auth.mfa.challenge-email', [
            'maskedEmail' => $security->maskEmail($user->email),
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

        if ($user->email_verified_at === null) {
            return back()->withErrors(['email_otp' => 'Email fallback is not available.']);
        }

        if ($limiter->tooMany($request, $user, 'login-email-send', 3)) {
            $security->record($request, $user, '2FA_EMAIL_RATE_LIMITED', 'Email OTP sending rate limited');

            return back()->withErrors(['email_otp' => 'Too many requests. Please try again later.']);
        }

        $limiter->hit($request, $user, 'login-email-send', 300);

        try {
            $otp->send($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN);
        } catch (Throwable) {
            $security->record($request, $user, '2FA_EMAIL_FAILED', 'Email OTP delivery failed');

            return back()->withErrors(['email_otp' => 'The verification email could not be sent. Use your authenticator or recovery code.']);
        }

        $security->record($request, $user, '2FA_EMAIL_SENT', 'Email OTP sent');

        return back()->with('status', 'verification-code-sent');
    }

    public function verifyEmail(
        Request $request,
        EmailOtpService $otp,
        MfaAttemptLimiter $limiter,
        MfaSessionService $mfaSession,
        MfaSecurityService $security,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();

        if ($limiter->tooMany($request, $user, 'login-email-verify')) {
            $security->record($request, $user, '2FA_EMAIL_RATE_LIMITED', 'Email OTP verification rate limited');
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, 'login-email-verify');

        if ($user->email_verified_at === null
            || ! $otp->verify($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN, $validated['code'])) {
            $security->record($request, $user, '2FA_EMAIL_FAILED', 'Email OTP verification failed');
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
        }

        $limiter->clear($request, $user, 'login-email-verify');
        $mfaSession->markVerified($request, $user);
        $security->record($request, $user, '2FA_EMAIL_SUCCESS', 'Email OTP verification succeeded');
        $logger->record($request, 'login', 'successful', $user, $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function recovery(): View
    {
        return view('auth.mfa.challenge-recovery');
    }

    public function verifyRecoveryCode(
        Request $request,
        RecoveryCodeService $recovery,
        MfaAttemptLimiter $limiter,
        MfaSessionService $mfaSession,
        MfaSecurityService $security,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validate(['recovery_code' => ['required', 'string', 'max:80']]);

        return $this->completeRecovery(
            $request,
            [$validated['recovery_code']],
            false,
            $recovery,
            $limiter,
            $mfaSession,
            $security,
            $logger,
        );
    }

    public function verifyRecoveryFile(
        Request $request,
        RecoveryCodeService $recovery,
        MfaAttemptLimiter $limiter,
        MfaSessionService $mfaSession,
        MfaSecurityService $security,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validate([
            'recovery_file' => ['required', 'file', 'max:'.(int) config('mfa.recovery_file_max_kilobytes', 64)],
        ]);
        $file = $validated['recovery_file'];
        $allowedMimes = ['text/plain', 'application/octet-stream'];

        if (strtolower($file->getClientOriginalExtension()) !== 'txt'
            || ! in_array((string) $file->getMimeType(), $allowedMimes, true)) {
            throw ValidationException::withMessages(['recovery_file' => 'Emergency recovery could not be verified.']);
        }

        $contents = $file->get();
        $candidates = $recovery->candidatesFromFile($contents);
        unset($contents);

        return $this->completeRecovery(
            $request,
            $candidates,
            true,
            $recovery,
            $limiter,
            $mfaSession,
            $security,
            $logger,
        );
    }

    /** @param list<string> $candidates */
    private function completeRecovery(
        Request $request,
        array $candidates,
        bool $fromFile,
        RecoveryCodeService $recovery,
        MfaAttemptLimiter $limiter,
        MfaSessionService $mfaSession,
        MfaSecurityService $security,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $action = $fromFile ? 'login-recovery-file' : 'login-recovery-code';

        if ($limiter->tooMany($request, $user, $action)) {
            throw ValidationException::withMessages(['recovery_code' => 'Too many attempts. Please try again later.']);
        }

        $limiter->hit($request, $user, $action);
        $verified = false;

        foreach (array_slice($candidates, 0, 20) as $candidate) {
            if ($recovery->consume($user, $candidate)) {
                $verified = true;
                break;
            }
        }

        if (! $verified) {
            $security->record(
                $request,
                $user,
                $fromFile ? '2FA_RECOVERY_FILE_FAILED' : '2FA_RECOVERY_FAILED',
                'Emergency recovery verification failed',
            );
            throw ValidationException::withMessages([
                $fromFile ? 'recovery_file' : 'recovery_code' => 'Emergency recovery could not be verified.',
            ]);
        }

        $limiter->clear($request, $user, $action);
        $mfaSession->markVerified($request, $user);
        $security->record(
            $request,
            $user,
            $fromFile ? '2FA_RECOVERY_FILE_USED' : '2FA_RECOVERY_USED',
            'Emergency recovery credential used',
        );
        $security->notify($user, 'An emergency recovery code was used to access your account.');
        $logger->record($request, 'login', 'successful', $user, $user->email);

        return redirect()->intended(route('dashboard'));
    }
}
