<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class IpWhoisService
{
    public function lookup(string $ip): ?array
    {
        $normalizedIp = trim((string) $ip);

        if ($normalizedIp === '' || strtolower($normalizedIp) === 'localhost') {
            return null;
        }

        if (! $this->isPublicIp($normalizedIp)) {
            return null;
        }

        $cacheKey = 'intsec:ip-location:'.$normalizedIp;

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($normalizedIp) {
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

                $latitude = (float) ($payload['latitude'] ?? 0);
                $longitude = (float) ($payload['longitude'] ?? 0);

                if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                    return null;
                }

                $connection = is_array($payload['connection'] ?? null) ? $payload['connection'] : [];
                $timezone = is_array($payload['timezone'] ?? null) ? $payload['timezone'] : [];

                return [
                    'ip' => $payload['ip'] ?? $normalizedIp,
                    'country' => $payload['country'] ?? null,
                    'country_code' => $payload['country_code'] ?? null,
                    'region' => $payload['region'] ?? null,
                    'region_code' => $payload['region_code'] ?? null,
                    'city' => $payload['city'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'postal' => $payload['postal'] ?? null,
                    'isp' => $connection['isp'] ?? null,
                    'organization' => $connection['org'] ?? $connection['organization'] ?? null,
                    'asn' => $connection['asn'] ?? null,
                    'timezone' => $timezone['id'] ?? $timezone['name'] ?? null,
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }

    protected function isPublicIp(string $ip): bool
    {
        $ip = trim(strtolower($ip));

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (str_contains($ip, ':')) {
            return ! $this->isPrivateIpv6($ip);
        }

        return ! $this->isPrivateIpv4($ip);
    }

    protected function isPrivateIpv4(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $privateRanges = [
            ['0.0.0.0', '0.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
        ];

        foreach ($privateRanges as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);

            if ($startLong !== false && $endLong !== false && $long >= $startLong && $long <= $endLong) {
                return true;
            }
        }

        return false;
    }

    protected function isPrivateIpv6(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        }

        $binary = inet_pton($ip);

        if ($binary === false) {
            return true;
        }

        $firstByte = ord($binary[0]);

        if ($firstByte === 0x00 && isset($binary[1]) && ord($binary[1]) === 0x00) {
            return true;
        }

        if ($firstByte === 0x20 || $firstByte === 0x21 || $firstByte === 0x22 || $firstByte === 0x23 || $firstByte === 0x24 || $firstByte === 0x25 || $firstByte === 0x26 || $firstByte === 0x27 || $firstByte === 0x28 || $firstByte === 0x29 || $firstByte === 0x2a || $firstByte === 0x2b || $firstByte === 0x2c || $firstByte === 0x2d || $firstByte === 0x2e || $firstByte === 0x2f) {
            return false;
        }

        if ($firstByte === 0xFC || $firstByte === 0xFD) {
            return true;
        }

        if ($firstByte === 0xFE && isset($binary[1]) && (ord($binary[1]) & 0xC0) === 0x80) {
            return true;
        }

        if ($ip === '::1') {
            return true;
        }

        return false;
    }
}
