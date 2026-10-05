<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class MfaAttemptLimiter
{
    public function __construct(private ClientIpResolver $clientIpResolver) {}

    public function tooMany(Request $request, User $user, string $action, ?int $maxAttempts = null): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->key($request, $user, $action),
            $maxAttempts ?? (int) config('mfa.max_attempts', 5),
        );
    }

    public function hit(Request $request, User $user, string $action, int $decaySeconds = 60): void
    {
        RateLimiter::hit($this->key($request, $user, $action), $decaySeconds);
    }

    public function clear(Request $request, User $user, string $action): void
    {
        RateLimiter::clear($this->key($request, $user, $action));
    }

    private function key(Request $request, User $user, string $action): string
    {
        $ip = $this->clientIpResolver->resolve($request)['ip'] ?? 'unknown';

        return 'mfa:'.$action.':'.$user->getKey().':'.hash('sha256', $ip.'|'.$request->session()->getId());
    }
}
