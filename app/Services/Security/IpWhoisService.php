<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class IpWhoisService
{
    public function __construct(private IpClassifier $ipClassifier) {}

    public function lookup(string $ip): ?array
    {
        $normalizedIp = trim((string) $ip);

        if ($normalizedIp === '' || strtolower($normalizedIp) === 'localhost') {
            return null;
        }

        if (! $this->ipClassifier->isPublic($normalizedIp)) {
            return null;
        }

        $cacheKey = 'intsec:ip-location:'.$normalizedIp;

        return Cache::remember($cacheKey, now()->addHours((int) config('intsec.ip_intelligence.cache_hours', 168)), function () use ($normalizedIp) {
            try {
                $response = Http::timeout(5)
                    ->connectTimeout(5)
                    ->acceptJson()
                    ->get('https://ipwho.is/'.$normalizedIp);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json();

                if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
                    return null;
                }

                $latitude = $this->sanitizeLocationCoordinate(data_get($payload, 'latitude'), 'latitude');
                $longitude = $this->sanitizeLocationCoordinate(data_get($payload, 'longitude'), 'longitude');

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                $connection = is_array($payload['connection'] ?? null) ? $payload['connection'] : [];
                $timezone = is_array($payload['timezone'] ?? null) ? $payload['timezone'] : [];

                $isp = $this->normalizeTextValue($connection['isp'] ?? null);
                $organization = $this->normalizeTextValue($connection['org'] ?? $connection['organization'] ?? null);

                return [
                    'ip' => $payload['ip'] ?? $normalizedIp,
                    'country' => $this->normalizeTextValue($payload['country'] ?? null),
                    'country_code' => $this->normalizeTextValue($payload['country_code'] ?? null),
                    'region' => $this->normalizeTextValue($payload['region'] ?? null),
                    'region_code' => $this->normalizeTextValue($payload['region_code'] ?? null),
                    'city' => $this->normalizeTextValue($payload['city'] ?? null),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'postal' => $this->normalizeTextValue($payload['postal'] ?? null),
                    'isp' => $isp,
                    'organization' => $organization,
                    'asn' => $connection['asn'] ?? null,
                    'timezone' => $this->normalizeTextValue($timezone['id'] ?? $timezone['name'] ?? null),
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }

    protected function sanitizeLocationCoordinate(mixed $value, string $axis): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        if (! is_finite($coordinate)) {
            return null;
        }

        if ($coordinate === 0.0) {
            return null;
        }

        if ($axis === 'latitude' && ($coordinate < -90 || $coordinate > 90)) {
            return null;
        }

        if ($axis === 'longitude' && ($coordinate < -180 || $coordinate > 180)) {
            return null;
        }

        return $coordinate;
    }

    protected function normalizeTextValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
