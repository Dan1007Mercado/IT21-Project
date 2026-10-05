<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AuthActivityLogger;
use App\Services\Security\MfaSecurityService;
use App\Services\Security\MfaSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecoveryCodesController extends Controller
{
    public function show(Request $request, MfaSessionService $mfaSession): View|RedirectResponse
    {
        $bundle = $mfaSession->recoveryBundle($request);

        if ($bundle === null) {
            return redirect()->route($request->user()->hasMfaConfigured() ? 'profile.edit' : 'mfa.enrollment.show');
        }

        return view('auth.mfa.recovery-codes', [
            'codes' => $bundle['codes'],
            'context' => $bundle['context'],
        ]);
    }

    public function download(Request $request, MfaSessionService $mfaSession, MfaSecurityService $security): StreamedResponse
    {
        $bundle = $mfaSession->recoveryBundle($request);

        abort_if($bundle === null, 404);

        /** @var User $user */
        $user = $request->user();
        $text = view('auth.mfa.recovery-file', [
            'codes' => $bundle['codes'],
            'maskedEmail' => $security->maskEmail($user->email),
            'generatedAt' => now(),
        ])->render();

        return response()->streamDownload(
            static function () use ($text): void {
                echo $text;
            },
            'INTSEC-Recovery-Codes-'.now()->format('Y-m-d').'.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store, private'],
        );
    }

    public function finish(
        Request $request,
        MfaSessionService $mfaSession,
        AuthActivityLogger $logger,
    ): RedirectResponse {
        $request->validate(['acknowledged' => ['accepted']]);
        $bundle = $mfaSession->recoveryBundle($request);

        if ($bundle === null) {
            throw ValidationException::withMessages(['acknowledged' => 'Recovery codes are no longer available.']);
        }

        /** @var User $user */
        $user = $request->user();
        $mfaSession->forgetRecoveryBundle($request);

        if ($bundle['context'] === 'enrollment') {
            $user->forceFill(['recovery_codes_confirmed_at' => now()])->save();
            $mfaSession->markVerified($request, $user);
            $logger->record($request, 'login', 'successful', $user, $user->email);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->route('profile.edit')->with('status', 'recovery-codes-regenerated');
    }
}
