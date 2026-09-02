<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginProtectionService
{
    public function isBlocked(Request $request): bool
    {
        $ipAddress = $request->ip() ?? '';

        return BlockedIp::isBlocked($ipAddress);
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
            SecurityEvent::record(
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
        }

        return true;
    }

    public function blockIp(Request $request, int $durationMinutes, string $reason): bool
    {
        $ipAddress = $request->ip() ?? '';

        if ($ipAddress === '') {
            return false;
        }

        $existing = BlockedIp::query()->where('ip_address', $ipAddress)->where('status', 'active')->first();

        if ($existing && $existing->isActive()) {
            return false;
        }

        $blocked = BlockedIp::query()->create([
            'ip_address' => $ipAddress,
            'reason' => $reason,
            'administrator_id' => null,
            'blocked_at' => now(),
            'expires_at' => now()->addMinutes($durationMinutes),
            'status' => 'active',
        ]);

        AuditLog::record(
            'ip_blocked',
            'blocked_ip',
            'Blocked IP',
            $blocked->id,
            null,
            [
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'expires_at' => $blocked->expires_at?->toISOString(),
                'status' => 'active',
            ],
            'Temporary blocking was automatically applied after repeated failed authentication attempts.',
            $ipAddress,
            $request->user(),
        );

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
