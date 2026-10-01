<?php

namespace App\Http\Middleware;

use App\Services\Security\AuthActivityLogger;
use App\Services\Security\ClientIpResolver;
use App\Services\Security\IpManagementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureIpNotBlocked
{
    public function __construct(
        protected IpManagementService $ipManagementService,
        protected AuthActivityLogger $authActivityLogger,
        protected ClientIpResolver $clientIpResolver,
    ) {}

    /**
     * @param  Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Centralized IP access-control decision (CIDR-aware, deny-wins).
        $ipAddress = $this->clientIpResolver->resolve($request)['ip'] ?? '';

        if ($this->ipManagementService->isBlocked($ipAddress)) {
            // Telemetry must not make access-control enforcement wait for an
            // external IP intelligence lookup.
            $this->authActivityLogger->record(
                $request,
                'login',
                'failed',
                null,
                $request->input('email'),
                'ip_blocked',
                false,
            );

            return response()->json([
                'message' => 'This IP address is temporarily blocked due to repeated failed login attempts.',
            ], 429);
        }

        return $next($request);
    }
}
