<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Http\Request;

class LoginProtectionService
{
    public function __construct(
        protected IpManagementService $ipManagementService,
        protected ClientIpResolver $clientIpResolver,
    ) {}

    public function isBlocked(Request $request): bool
    {
        $ipAddress = $this->clientIpResolver->resolve($request)['ip'] ?? '';

        // Centralized decision (CIDR-aware, allow/block precedence).
        return $this->ipManagementService->isBlocked($ipAddress);
    }

    public function recordFailedAttempt(Request $request, ?User $user, ?string $attemptedIdentity): bool
    {
        $ipAddress = $this->clientIpResolver->resolve($request)['ip'] ?? '';
        $attemptedIdentity ??= '';

        $maxAttempts = IntsecSettings::getInt('max_login_attempts', 5);
        $windowMinutes = IntsecSettings::getInt('login_attempt_window_minutes', 5);

        $windowStart = now()->subMinutes($windowMinutes);

        $recentFailures = AuthenticationLog::query()
            ->where('action', 'login')
            ->where('status', 'failed')
            ->where('occurred_at', '>=', $windowStart)
            ->where('ip_address', $ipAddress)
            ->count();

        if ($recentFailures < $maxAttempts) {
            return false;
        }

        $blockDuration = IntsecSettings::getInt('login_block_duration_minutes', 15);
        $blocked = $this->blockIp($request, $blockDuration, 'Too many failed login attempts');

        return $blocked || $this->ipManagementService->isBlocked($ipAddress);
    }

    public function blockIp(Request $request, int $durationMinutes, string $reason): bool
    {
        $ipAddress = $this->clientIpResolver->resolve($request)['ip'] ?? '';

        if ($ipAddress === '') {
            return false;
        }

        $normalized = IpNetwork::normalize($ipAddress);

        if ($normalized === null) {
            return false;
        }

        // Reuse the central engine so automatic blocks deduplicate
        // against manual/CIDR rules and keep full audit traceability.
        if ($this->ipManagementService->findEnforcingBlock($normalized)) {
            return false;
        }

        try {
            $this->ipManagementService->createRule([
                'ip_address' => $normalized,
                'action' => BlockedIp::ACTION_BLOCK,
                'is_enabled' => true,
                'source' => 'automatic',
                'reason' => $reason,
                'expires_at' => now()->addMinutes($durationMinutes),
            ], $request->user(), $ipAddress);
        } catch (\RuntimeException) {
            // A concurrent request may have created the same active rule.
            return false;
        }

        return true;
    }

    public function unblockIp(Request $request, BlockedIp $blockedIp, string $reason = 'Administrative unblock', ?User $administrator = null): void
    {
        $old = [
            'ip_address' => $blockedIp->ip_address,
            'reason' => $blockedIp->reason,
            'status' => $blockedIp->status,
            'expires_at' => $blockedIp->expires_at?->toISOString(),
        ];

        $blockedIp->status = 'inactive';
        $blockedIp->reason = $reason;
        $blockedIp->save();

        AuditLog::record(
            'ip_unblocked',
            'blocked_ip',
            'Blocked IP',
            $blockedIp->id,
            $old,
            [
                'ip_address' => $blockedIp->ip_address,
                'reason' => $reason,
                'status' => 'inactive',
                'expires_at' => $blockedIp->expires_at?->toISOString(),
            ],
            'Blocked IP record was manually released.',
            $this->clientIpResolver->resolve($request)['ip'],
            $administrator,
        );
    }
}
