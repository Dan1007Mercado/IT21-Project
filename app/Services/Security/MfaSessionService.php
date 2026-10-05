<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;

final class MfaSessionService
{
    private const VERIFIED_USER_ID = 'mfa.verified_user_id';

    private const VERIFIED_AT = 'mfa.verified_at';

    private const RECOVERY_BUNDLE = 'mfa.recovery_bundle';

    public function reset(Request $request): void
    {
        $request->session()->forget([
            self::VERIFIED_USER_ID,
            self::VERIFIED_AT,
            self::RECOVERY_BUNDLE,
        ]);
    }

    public function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            self::VERIFIED_USER_ID => $user->getKey(),
            self::VERIFIED_AT => now()->timestamp,
        ]);
    }

    public function isVerified(Request $request, User $user): bool
    {
        return (int) $request->session()->get(self::VERIFIED_USER_ID) === (int) $user->getKey()
            && is_numeric($request->session()->get(self::VERIFIED_AT));
    }

    public function isRecentlyVerified(Request $request, User $user): bool
    {
        if (! $this->isVerified($request, $user)) {
            return false;
        }

        return (int) $request->session()->get(self::VERIFIED_AT)
            >= now()->subSeconds((int) config('mfa.recent_verification_seconds', 600))->timestamp;
    }

    /** @param list<string> $codes */
    public function storeRecoveryBundle(Request $request, array $codes, string $context): void
    {
        $payload = encrypt(json_encode([
            'codes' => $codes,
            'context' => $context,
            'created_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));

        $request->session()->put(self::RECOVERY_BUNDLE, $payload);
    }

    /** @return array{codes: list<string>, context: string, created_at: int}|null */
    public function recoveryBundle(Request $request): ?array
    {
        $encrypted = $request->session()->get(self::RECOVERY_BUNDLE);

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            $payload = json_decode(decrypt($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->forgetRecoveryBundle($request);

            return null;
        }

        if (! is_array($payload) || ! is_array($payload['codes'] ?? null)
            || ! is_string($payload['context'] ?? null) || ! is_int($payload['created_at'] ?? null)
            || $payload['created_at'] < now()->subMinutes(30)->timestamp) {
            $this->forgetRecoveryBundle($request);

            return null;
        }

        return $payload;
    }

    public function forgetRecoveryBundle(Request $request): void
    {
        $request->session()->forget(self::RECOVERY_BUNDLE);
    }
}
