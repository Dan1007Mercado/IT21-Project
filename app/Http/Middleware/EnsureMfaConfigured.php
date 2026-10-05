<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfaConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasMfaConfigured()) {
            return redirect()->guest(route('mfa.enrollment.show'));
        }

        return $next($request);
    }
}
