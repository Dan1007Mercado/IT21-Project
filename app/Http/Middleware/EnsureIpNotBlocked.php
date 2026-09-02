<?php

namespace App\Http\Middleware;

use App\Services\Security\LoginProtectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureIpNotBlocked
{
    public function __construct(protected LoginProtectionService $loginProtectionService)
    {
    }

    /**
     * @param  \Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if ($this->loginProtectionService->isBlocked($request)) {
            return response()->json([
                'message' => 'This IP address is temporarily blocked due to repeated failed login attempts.',
            ], 429);
        }

        return $next($request);
    }
}
