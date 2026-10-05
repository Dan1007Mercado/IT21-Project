<?php

namespace App\Services\Security;

use App\Mail\MfaOtpCodeMail;
use App\Models\MfaOtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class EmailOtpService
{
    public function send(User $user, string $purpose): void
    {
        $this->assertPurpose($purpose);

        if ($purpose === MfaOtpChallenge::PURPOSE_MFA_LOGIN && $user->email_verified_at === null) {
            throw new RuntimeException('A verified email address is required.');
        }

        $latest = MfaOtpChallenge::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->latest('last_sent_at')
            ->first();

        $cooldown = (int) config('mfa.email_otp_resend_cooldown', 60);
        if ($latest?->last_sent_at?->isAfter(now()->subSeconds($cooldown))) {
            throw new RuntimeException('Please wait before requesting another code.');
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $purpose, $code): void {
            MfaOtpChallenge::query()
                ->where('user_id', $user->getKey())
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            MfaOtpChallenge::query()->create([
                'user_id' => $user->getKey(),
                'purpose' => $purpose,
                'otp_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds((int) config('mfa.email_otp_ttl', 300)),
                'last_sent_at' => now(),
            ]);

            Mail::to($user->email)->send(new MfaOtpCodeMail($code, $purpose));
        });
    }

    public function verify(User $user, string $purpose, string $code): bool
    {
        $this->assertPurpose($purpose);

        return DB::transaction(function () use ($user, $purpose, $code): bool {
            $challenge = MfaOtpChallenge::query()
                ->where('user_id', $user->getKey())
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->expires_at->isPast()
                || $challenge->attempt_count >= (int) config('mfa.max_attempts', 5)) {
                return false;
            }

            $challenge->increment('attempt_count');

            if (! preg_match('/^\d{6}$/', $code) || ! Hash::check($code, $challenge->otp_hash)) {
                return false;
            }

            $challenge->forceFill(['used_at' => now()])->save();

            return true;
        });
    }

    private function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, [
            MfaOtpChallenge::PURPOSE_EMAIL_VERIFICATION,
            MfaOtpChallenge::PURPOSE_MFA_LOGIN,
        ], true)) {
            throw new RuntimeException('Unsupported verification purpose.');
        }
    }
}
