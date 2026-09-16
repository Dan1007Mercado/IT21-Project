<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntsecApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('intsec.api_token');
        $providedToken = (string) $request->bearerToken();

        if ($configuredToken === '') {
            return response()->json(['message' => 'Security event intake is not configured.'], 503);
        }

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
