<?php

namespace App\Http\Middleware;

use App\Services\Security\MfaSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfaVerified
{
    public function __construct(private MfaSessionService $mfaSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->mfaSession->isVerified($request, $user)) {
            return redirect()->guest(route('mfa.challenge.show'));
        }

        return $next($request);
    }
}
