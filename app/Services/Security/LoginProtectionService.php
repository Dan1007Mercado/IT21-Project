<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginProtectionService
{
    public function __construct(protected IpManagementService $ipManagementService)
    {
    }

    public function isBlocked(Request $request): bool
    {
        $ipAddress = $request->ip() ?? '';

        // Centralized decision (CIDR-aware, allow/block precedence).
        return $this->ipManagementService->isBlocked($ipAddress);
    }

    public function recordFailedAttempt(Request $request, ?User $user, ?string $attemptedIdentity): bool
    {
        $ipAddress = (string) ($request->ip() ?? '');
        $attemptedIdentity ??= '';

        $maxAttempts = IntsecSettings::getInt('max_login_attempts', 5);
        $windowMinutes = IntsecSettings::getInt('login_attempt_window_minutes', 5);

        $windowStart = now()->subMinutes($windowMinutes);

        $recentFailures = AuthenticationLog::query()
            ->where('action', 'login')
            ->where('status', 'failed')
            ->where('occurred_at', '>=', $windowStart)
            ->where(function ($query) use ($ipAddress, $attemptedIdentity, $user) {
                $query->where('ip_address', $ipAddress);

                if ($user) {
                    $query->orWhere('user_id', $user->id);
                }

                if ($attemptedIdentity !== '') {
                    $query->orWhere('attempted_identity', $attemptedIdentity);
                }
            })
            ->count();

        if ($recentFailures < $maxAttempts) {
            return false;
        }

        $blockDuration = IntsecSettings::getInt('login_block_duration_minutes', 15);
        $blocked = $this->blockIp($request, $blockDuration, 'Too many failed login attempts');

        if ($blocked) {
            $event = SecurityEvent::record(
                'Repeated failed authentication',
                'authentication',
                'High',
                $user,
                $ipAddress,
                [
                    'attempted_identity' => $attemptedIdentity,
                    'failed_attempt_count' => $recentFailures,
                    'threshold' => $maxAttempts,
                    'window_minutes' => $windowMinutes,
                ],
                'Multiple failed login attempts exceeded the configured threshold and triggered an automatic temporary block.'
            );

            SecurityAlert::query()->create([
                'alert_id' => SecurityAlert::generateAlertId(),
                'title' => 'Brute-force login threshold exceeded',
                'alert_type' => SecurityAlert::TYPE_BRUTE_FORCE,
                'severity' => 'High',
                'description' => "{$recentFailures} failed login attempts from {$ipAddress} exceeded the configured threshold of {$maxAttempts} within {$windowMinutes} minutes.",
                'security_event_id' => $event->id,
                'source_ip' => $ipAddress,
                'metadata' => [
                    'detection_rule' => 'repeated_authentication_threshold',
                    'failed_attempt_count' => $recentFailures,
                    'threshold' => $maxAttempts,
                    'window_minutes' => $windowMinutes,
                ],
                'status' => 'new',
                'occurred_at' => now(),
            ]);
        }

        return true;
    }

    public function blockIp(Request $request, int $durationMinutes, string $reason): bool
    {
        $ipAddress = $request->ip() ?? '';

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
            $request->ip(),
            $administrator,
        );
    }
}
