<?php

namespace App\Services\Security;

use App\Jobs\EnrichIpAddressJob;
use App\Models\AuthenticationLog;
use App\Models\IpIntelligence;

final class IpEnrichmentService
{
    public function __construct(
        private IpClassifier $classifier,
        private IpWhoisService $whois,
    ) {}

    public function observe(?string $ip): ?IpIntelligence
    {
        $canonical = $ip === null ? null : IpNetwork::canonicalIp($ip);
        if ($canonical === null) {
            return null;
        }

        $record = IpIntelligence::query()->updateOrCreate(
            ['ip_address' => $canonical],
            ['ip_type' => $this->classifier->classify($canonical), 'last_seen_at' => now()],
        );

        if ($record->ip_type === IpClassifier::PUBLIC
            && IntsecSettings::getBool('ip_enrichment_enabled', (bool) config('intsec.ip_intelligence.enabled', false))
            && ($record->last_enriched_at === null || $record->last_enriched_at->lt(now()->subHours(IntsecSettings::getInt('ip_enrichment_cache_hours', 168))))
        ) {
            EnrichIpAddressJob::dispatch($canonical)->afterResponse();
        }

        return $record;
    }

    public function enrich(string $ip, bool $force = false): ?IpIntelligence
    {
        $canonical = IpNetwork::canonicalIp($ip);
        if ($canonical === null || ! $this->classifier->isPublic($canonical)) {
            return null;
        }

        $record = IpIntelligence::query()->firstOrNew(['ip_address' => $canonical]);
        $record->ip_type = IpClassifier::PUBLIC;

        $hours = max(1, IntsecSettings::getInt('ip_enrichment_cache_hours', 168));
        if (! $force && $record->last_enriched_at?->gte(now()->subHours($hours))) {
            return $record;
        }

        $location = $this->whois->lookup($canonical);
        if (! is_array($location)) {
            return $record->exists ? $record : null;
        }

        $record->fill([
            'country' => $location['country'] ?? null,
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'region_code' => $location['region_code'] ?? null,
            'city' => $location['city'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'postal' => $location['postal'] ?? null,
            'timezone' => $location['timezone'] ?? null,
            'asn' => $location['asn'] ?? null,
            'isp' => $location['isp'] ?? null,
            'organization' => $location['organization'] ?? null,
            'provider' => 'ipwho.is',
            'last_enriched_at' => now(),
            'last_seen_at' => $record->last_seen_at ?? now(),
        ]);
        $record->save();

        // Retain compatibility with the existing authentication investigation
        // fields while the normalized intelligence record remains authoritative.
        AuthenticationLog::query()->where('ip_address', $canonical)->update([
            'country' => $record->country, 'country_code' => $record->country_code,
            'region' => $record->region, 'region_code' => $record->region_code,
            'city' => $record->city, 'latitude' => $record->latitude,
            'longitude' => $record->longitude, 'postal' => $record->postal,
            'timezone' => $record->timezone, 'asn' => $record->asn,
            'isp' => $record->isp, 'organization' => $record->organization,
        ]);

        return $record;
    }
}
