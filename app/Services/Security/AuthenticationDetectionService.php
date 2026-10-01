<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\SecurityAlert;

final class AuthenticationDetectionService
{
    public function __construct(private DetectionEngine $engine) {}

    /** @return array<int, SecurityAlert> */
    public function evaluate(AuthenticationLog $log): array
    {
        if ($log->action !== 'login') {
            return [];
        }

        return $log->status === 'failed'
            ? $this->evaluateFailure($log)
            : $this->evaluateSuccess($log);
    }

    /** @return array<int, SecurityAlert> */
    private function evaluateFailure(AuthenticationLog $log): array
    {
        $windowMinutes = IntsecSettings::getInt('login_attempt_window_minutes', 5);
        $since = $log->occurred_at->copy()->subMinutes($windowMinutes);
        $identity = $this->identity($log);
        $failures = AuthenticationLog::query()->where('action', 'login')->where('status', 'failed')
            ->whereBetween('occurred_at', [$since, $log->occurred_at]);
        $alerts = [];

        $ipThreshold = IntsecSettings::getInt('repeated_authentication_threshold', 5);
        $ipCount = (clone $failures)->where('ip_address', $log->ip_address)->count();
        if ($ipCount >= $ipThreshold) {
            $alerts[] = $this->record($log, 'auth.repeated_ip_failures', 'Repeated authentication failures from one IP', SecurityAlert::TYPE_REPEATED_AUTH_FAILURES, 'High', $ipCount, $ipThreshold, $windowMinutes, $log->ip_address);
        }

        if ($identity !== null) {
            $accountThreshold = IntsecSettings::getInt('brute_force_threshold', 5);
            $accountCount = (clone $failures)->whereRaw('LOWER(attempted_identity) = ?', [mb_strtolower($identity)])->count();
            if ($accountCount >= $accountThreshold) {
                $alerts[] = $this->record($log, 'auth.account_brute_force', 'Repeated failures against one account', SecurityAlert::TYPE_AUTH_BRUTE_FORCE, 'High', $accountCount, $accountThreshold, $windowMinutes, mb_strtolower($identity), ['affected_identity' => $identity]);
            }

            $distributedThreshold = IntsecSettings::getInt('distributed_attack_threshold', 3);
            $distinctIps = (clone $failures)->whereRaw('LOWER(attempted_identity) = ?', [mb_strtolower($identity)])->distinct()->count('ip_address');
            if ($distinctIps >= $distributedThreshold) {
                $alerts[] = $this->record($log, 'auth.distributed_account_attack', 'One account attacked from multiple IPs', SecurityAlert::TYPE_DISTRIBUTED_ACCOUNT_ATTACK, 'High', $distinctIps, $distributedThreshold, $windowMinutes, mb_strtolower($identity), ['affected_identity' => $identity, 'distinct_ip_count' => $distinctIps]);
            }
        }

        $sprayThreshold = IntsecSettings::getInt('password_spray_threshold', 5);
        $distinctIdentities = (clone $failures)->where('ip_address', $log->ip_address)
            ->whereNotNull('attempted_identity')->distinct()->count('attempted_identity');
        if ($distinctIdentities >= $sprayThreshold) {
            $alerts[] = $this->record($log, 'auth.password_spray', 'Many identities attempted from one IP', SecurityAlert::TYPE_PASSWORD_SPRAY, 'High', $distinctIdentities, $sprayThreshold, $windowMinutes, $log->ip_address, ['distinct_identity_count' => $distinctIdentities]);
        }

        return $alerts;
    }

    /** @return array<int, SecurityAlert> */
    private function evaluateSuccess(AuthenticationLog $log): array
    {
        $alerts = [];
        $windowMinutes = IntsecSettings::getInt('login_attempt_window_minutes', 5);
        $since = $log->occurred_at->copy()->subMinutes($windowMinutes);
        $identity = $this->identity($log);
        $recentFailures = AuthenticationLog::query()->where('action', 'login')->where('status', 'failed')
            ->whereBetween('occurred_at', [$since, $log->occurred_at])
            ->where(function ($query) use ($log, $identity): void {
                $query->where('ip_address', $log->ip_address);
                if ($identity !== null) {
                    $query->orWhereRaw('LOWER(attempted_identity) = ?', [mb_strtolower($identity)]);
                }
            })->count();

        if ($recentFailures >= IntsecSettings::getInt('failed_login_warning_threshold', 3)) {
            $alerts[] = $this->record($log, 'auth.failed_then_success', 'Failed authentication followed by success', SecurityAlert::TYPE_FAILED_THEN_SUCCESS, 'High', $recentFailures, IntsecSettings::getInt('failed_login_warning_threshold', 3), $windowMinutes, $log->ip_address.'|'.mb_strtolower((string) $identity), ['affected_identity' => $identity]);
        }

        if ($log->user_id !== null) {
            $knownSuccess = AuthenticationLog::query()->where('action', 'login')->where('status', 'successful')
                ->where('user_id', $log->user_id)->where('id', '<', $log->id)->where('occurred_at', '>=', $log->occurred_at->copy()->subDays(30));
            $hasHistory = (clone $knownSuccess)->exists();
            $knownContext = (clone $knownSuccess)->where(function ($query) use ($log): void {
                $query->where('ip_address', $log->ip_address);
                if (filled($log->user_agent)) {
                    $query->orWhere('user_agent', $log->user_agent);
                }
            })->exists();

            if ($hasHistory && ! $knownContext) {
                $alerts[] = $this->record($log, 'auth.suspicious_success_context', 'Successful login from a new context', SecurityAlert::TYPE_SUSPICIOUS_LOGIN, 'Suspicious', 1, 1, 30 * 86400 / 60, (string) $log->user_id, ['affected_identity' => $identity, 'new_ip' => true, 'new_user_agent' => true]);
            }
        }

        return $alerts;
    }

    private function identity(AuthenticationLog $log): ?string
    {
        $identity = trim((string) $log->attempted_identity);

        return $identity === '' ? null : $identity;
    }

    private function record(AuthenticationLog $log, string $ruleKey, string $name, string $type, string $severity, int $count, int $threshold, int $windowMinutes, string $groupingKey, array $metadata = []): SecurityAlert
    {
        return $this->engine->record([
            'rule_key' => $ruleKey, 'rule_name' => $name, 'alert_type' => $type,
            'title' => $name, 'description' => "{$count} observations met threshold {$threshold} within {$windowMinutes} minutes.",
            'severity' => $severity, 'source_ip' => $log->ip_address, 'grouping_key' => $groupingKey,
            'threshold' => $threshold, 'window_seconds' => $windowMinutes * 60, 'observed_count' => $count,
            'affected_identity' => $metadata['affected_identity'] ?? null,
            'authentication_log_id' => $log->id, 'user_id' => $log->user_id, 'metadata' => $metadata,
        ]);
    }
}
