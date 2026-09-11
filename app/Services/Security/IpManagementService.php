<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Centralized IP access-control decision point for INTSEC.
 *
 * Precedence model (deny-wins, fail-closed):
 *   1. If any enforcing BLOCK rule matches the IP  -> blocked.
 *   2. Else if any enforcing ALLOW rule matches    -> allowed.
 *   3. Else                                        -> no rule (neutral).
 *
 * An ALLOW rule marks an IP as trusted by the access-control layer but
 * does NOT disable IDS detection, telemetry, or audit logging.
 */
final class IpManagementService
{
    public const DECISION_BLOCKED = 'blocked';

    public const DECISION_ALLOWED = 'allowed';

    public const DECISION_NONE = 'none';

    /**
     * @return array{decision: string, rule: ?BlockedIp}
     */
    public function decide(string $ip, bool $recordMatch = true): array
    {
        $ip = trim($ip);

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['decision' => self::DECISION_NONE, 'rule' => null];
        }

        $canonical = IpNetwork::canonicalIp($ip) ?? $ip;

        // Lightweight enforcement query: only the columns the decision
        // needs. No relations, no IP intelligence on this path.
        $rules = BlockedIp::query()->enforcing()->orderByDesc('blocked_at')->get(['id', 'ip_address', 'action']);

        $allowMatch = null;

        foreach ($rules as $rule) {
            if (! IpNetwork::matches($rule->ip_address, $canonical)) {
                continue;
            }

            // BLOCK wins immediately (deny-wins precedence).
            if ($rule->isBlockRule()) {
                if ($recordMatch) {
                    $this->recordMatch($rule);
                }

                return ['decision' => self::DECISION_BLOCKED, 'rule' => $rule];
            }

            $allowMatch ??= $rule;
        }

        if ($allowMatch) {
            if ($recordMatch) {
                $this->recordMatch($allowMatch);
            }

            return ['decision' => self::DECISION_ALLOWED, 'rule' => $allowMatch];
        }

        return ['decision' => self::DECISION_NONE, 'rule' => null];
    }

    public function isBlocked(string $ip): bool
    {
        return $this->decide($ip)['decision'] === self::DECISION_BLOCKED;
    }

    public function isAllowed(string $ip): bool
    {
        return $this->decide($ip)['decision'] === self::DECISION_ALLOWED;
    }

    /**
     * All enforcing rules currently matching an IP (for investigation UI).
     */
    public function matchingRules(string $ip): Collection
    {
        $ip = trim($ip);

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return collect();
        }

        $canonical = IpNetwork::canonicalIp($ip) ?? $ip;

        return BlockedIp::query()->enforcing()->orderByDesc('blocked_at')->get()
            ->filter(fn (BlockedIp $rule) => IpNetwork::matches($rule->ip_address, $canonical))
            ->values();
    }

    /**
     * @param array{ip_address: string, action?: string, reason?: ?string, description?: ?string, name?: ?string, source?: string, is_enabled?: bool, expires_at?: mixed, alert_id?: ?int, incident_id?: ?int} $data
     */
    public function createRule(array $data, ?User $actor = null, ?string $requestIp = null): BlockedIp
    {
        $normalized = IpNetwork::normalize($data['ip_address']);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Invalid IP address or CIDR range.');
        }

        $action = strtolower((string) ($data['action'] ?? BlockedIp::ACTION_BLOCK));
        $action = $action === BlockedIp::ACTION_ALLOW ? BlockedIp::ACTION_ALLOW : BlockedIp::ACTION_BLOCK;

        $this->assertNoActiveDuplicate($normalized, $action);

        $rule = BlockedIp::query()->create([
            'ip_address' => $normalized,
            'action' => $action,
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'source' => $this->normalizeSource($data['source'] ?? 'manual'),
            'name' => $data['name'] ?? null,
            'reason' => $data['reason'] ?? null,
            'description' => $data['description'] ?? null,
            'administrator_id' => $actor?->id,
            'alert_id' => $data['alert_id'] ?? null,
            'incident_id' => $data['incident_id'] ?? null,
            'blocked_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => 'active',
            'match_count' => 0,
        ]);

        $this->audit(
            $action === BlockedIp::ACTION_ALLOW ? 'ip_allowed' : 'ip_blocked',
            $rule,
            null,
            $this->snapshot($rule),
            ($action === BlockedIp::ACTION_ALLOW ? 'Allow' : 'Block')." rule created for {$normalized}.",
            $requestIp,
            $actor,
        );

        AuditLog::record(
            'ip_rule_created',
            'blocked_ip',
            $normalized,
            $rule->id,
            null,
            $this->snapshot($rule),
            "IP Management rule created ({$action}) for {$normalized}.",
            $requestIp,
            $actor,
        );

        return $rule;
    }

    public function updateRule(BlockedIp $rule, array $data, ?User $actor = null, ?string $requestIp = null): BlockedIp
    {
        $previous = $this->snapshot($rule);

        // Historical identity (IP value, source link, creator) is immutable;
        // only action / reason / description / expiration / enabled may change.
        foreach (['action', 'reason', 'description', 'name', 'expires_at', 'is_enabled', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                if ($field === 'action') {
                    $value = strtolower((string) $value) === BlockedIp::ACTION_ALLOW
                        ? BlockedIp::ACTION_ALLOW
                        : BlockedIp::ACTION_BLOCK;
                }

                if ($field === 'is_enabled') {
                    $value = (bool) $value;
                }

                $rule->{$field} = $value;
            }
        }

        $rule->save();

        AuditLog::record(
            'ip_rule_updated',
            'blocked_ip',
            $rule->ip_address,
            $rule->id,
            $previous,
            $this->snapshot($rule),
            "IP Management rule updated for {$rule->ip_address}.",
            $requestIp,
            $actor,
        );

        return $rule->fresh();
    }

    public function setEnabled(BlockedIp $rule, bool $enabled, ?User $actor = null, ?string $requestIp = null): BlockedIp
    {
        $previous = $this->snapshot($rule);
        $rule->is_enabled = $enabled;
        $rule->save();

        AuditLog::record(
            $enabled ? 'ip_rule_enabled' : 'ip_rule_disabled',
            'blocked_ip',
            $rule->ip_address,
            $rule->id,
            $previous,
            $this->snapshot($rule),
            $enabled ? "IP rule enabled for {$rule->ip_address}." : "IP rule disabled for {$rule->ip_address}.",
            $requestIp,
            $actor,
        );

        return $rule->fresh();
    }

    public function deleteRule(BlockedIp $rule, ?User $actor = null, ?string $requestIp = null): void
    {
        $snapshot = $this->snapshot($rule);
        $label = $rule->ip_address;
        $id = $rule->id;

        $rule->delete();

        AuditLog::record(
            'ip_rule_deleted',
            'blocked_ip',
            $label,
            $id,
            $snapshot,
            null,
            "IP Management rule deleted for {$label}.",
            $requestIp,
            $actor,
        );
    }

    /**
     * Block an IP originating from an alert investigation.
     * Idempotent: returns the existing enforcing block rule instead of a duplicate.
     */
    public function blockFromAlert(SecurityAlert $alert, array $data, ?User $actor = null, ?string $requestIp = null): BlockedIp
    {
        $ip = trim((string) ($data['ip_address'] ?? $alert->source_ip ?? ''));

        if ($ip === '') {
            throw new \InvalidArgumentException('Alert has no source IP to block.');
        }

        $normalized = IpNetwork::normalize($ip);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Alert source IP is not a valid IP address.');
        }

        if ($existing = $this->findEnforcingBlock($normalized)) {
            return $existing;
        }

        $rule = $this->createRule([
            'ip_address' => $normalized,
            'action' => BlockedIp::ACTION_BLOCK,
            'reason' => $data['reason'] ?? ('Blocked from alert '.($alert->alert_id ?? '')),
            'description' => $data['description'] ?? $alert->title,
            'source' => 'alert',
            'is_enabled' => true,
            'expires_at' => $data['expires_at'] ?? null,
            'alert_id' => $alert->id,
            'incident_id' => $data['incident_id'] ?? $alert->incident_id,
        ], $actor, $requestIp);

        AuditLog::record(
            'ip_auto_blocked',
            'blocked_ip',
            $normalized,
            $rule->id,
            null,
            array_merge($this->snapshot($rule), ['alert_id' => $alert->id]),
            "IP blocked from alert {$alert->alert_id}.",
            $requestIp,
            $actor,
        );

        return $rule;
    }

    /**
     * Block an IP originating from an incident investigation.
     * Idempotent: returns the existing enforcing block rule instead of a duplicate.
     */
    public function blockFromIncident(Incident $incident, array $data, ?User $actor = null, ?string $requestIp = null): BlockedIp
    {
        $ip = trim((string) ($data['ip_address'] ?? $incident->source_ip ?? ''));

        if ($ip === '') {
            throw new \InvalidArgumentException('Incident has no source IP to block.');
        }

        $normalized = IpNetwork::normalize($ip);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Incident source IP is not a valid IP address.');
        }

        if ($existing = $this->findEnforcingBlock($normalized)) {
            return $existing;
        }

        $rule = $this->createRule([
            'ip_address' => $normalized,
            'action' => BlockedIp::ACTION_BLOCK,
            'reason' => $data['reason'] ?? ('Blocked from incident '.($incident->incident_id ?? '')),
            'description' => $data['description'] ?? $incident->title,
            'source' => 'incident',
            'is_enabled' => true,
            'expires_at' => $data['expires_at'] ?? null,
            'incident_id' => $incident->id,
            'alert_id' => $data['alert_id'] ?? null,
        ], $actor, $requestIp);

        AuditLog::record(
            'ip_auto_blocked',
            'blocked_ip',
            $normalized,
            $rule->id,
            null,
            array_merge($this->snapshot($rule), ['incident_id' => $incident->id]),
            "IP blocked from incident {$incident->incident_id}.",
            $requestIp,
            $actor,
        );

        return $rule;
    }

    /**
     * Find an enforcing BLOCK rule for an exact normalized value,
     * or any enforcing CIDR block rule that already covers the IP.
     */
    public function findEnforcingBlock(string $normalizedOrIp): ?BlockedIp
    {
        $candidates = BlockedIp::query()->enforcing()
            ->where('action', BlockedIp::ACTION_BLOCK)
            ->orderByDesc('blocked_at')->get();

        foreach ($candidates as $rule) {
            if ($rule->ip_address === $normalizedOrIp) {
                return $rule;
            }

            if (IpNetwork::isCidr($rule->ip_address)
                && IpNetwork::matches($rule->ip_address, $normalizedOrIp)) {
                return $rule;
            }
        }

        return null;
    }

    protected function assertNoActiveDuplicate(string $normalized, string $action): void
    {
        $exists = BlockedIp::query()->enforcing()
            ->where('ip_address', $normalized)
            ->where('action', $action)
            ->exists();

        if ($exists) {
            throw new \RuntimeException("An active {$action} rule already exists for {$normalized}.");
        }
    }

    protected function normalizeSource(mixed $source): string
    {
        $source = strtolower(trim((string) $source));

        return in_array($source, BlockedIp::SOURCES, true) ? $source : 'manual';
    }

    protected function recordMatch(BlockedIp $rule): void
    {
        // Single atomic query: safe under concurrent requests.
        BlockedIp::query()->whereKey($rule->id)
            ->increment('match_count', 1, ['last_matched_at' => now()]);

        $rule->match_count = ((int) ($rule->match_count ?? 0)) + 1;
        $rule->last_matched_at = now();
    }

    protected function snapshot(BlockedIp $rule): array
    {
        return [
            'ip_address' => $rule->ip_address,
            'action' => $rule->action,
            'is_enabled' => (bool) $rule->is_enabled,
            'source' => $rule->source,
            'reason' => $rule->reason,
            'expires_at' => $rule->expires_at?->toISOString(),
            'status' => $rule->status,
        ];
    }

    protected function audit(string $action, BlockedIp $rule, ?array $previous, ?array $new, string $description, ?string $requestIp, ?User $actor): void
    {
        AuditLog::record(
            $action,
            'blocked_ip',
            $rule->ip_address,
            $rule->id,
            $previous,
            $new,
            $description,
            $requestIp,
            $actor,
        );
    }
}
