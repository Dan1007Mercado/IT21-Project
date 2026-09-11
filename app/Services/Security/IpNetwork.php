<?php

namespace App\Services\Security;

/**
 * IPv4 / IPv6 / CIDR parsing, normalization and subnet matching.
 *
 * Uses PHP's filter_var / inet_pton (not regex) for correctness.
 */
final class IpNetwork
{
    /**
     * Normalize user input so duplicate representations cannot create
     * confusing duplicate rules (e.g. uppercase IPv6, :: expansions).
     *
     * Returns the canonical string, or null when invalid.
     */
    public static function normalize(string $input): ?string
    {
        $value = trim($input);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            [$base, $prefix] = explode('/', $value, 2) + [null, null];

            $base = trim((string) $base);
            $prefix = trim((string) $prefix);

            if ($base === '' || $prefix === '' || ! ctype_digit($prefix)) {
                return null;
            }

            $prefixLength = (int) $prefix;

            if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                if ($prefixLength < 0 || $prefixLength > 32) {
                    return null;
                }

                return self::networkAddress($base, $prefixLength).'/'.$prefixLength;
            }

            if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if ($prefixLength < 0 || $prefixLength > 128) {
                    return null;
                }

                return self::networkAddress($base, $prefixLength).'/'.$prefixLength;
            }

            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }

        return self::canonicalIp($value);
    }

    /**
     * Return the network address for a validated IP/CIDR prefix.
     * This gives equivalent CIDR expressions one canonical stored value.
     */
    private static function networkAddress(string $ip, int $prefixLength): ?string
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $network = '';
        $bitsRemaining = $prefixLength;

        foreach (str_split($packed) as $byte) {
            if ($bitsRemaining >= 8) {
                $network .= $byte;
            } elseif ($bitsRemaining <= 0) {
                $network .= "\x00";
            } else {
                $mask = (0xFF << (8 - $bitsRemaining)) & 0xFF;
                $network .= chr(ord($byte) & $mask);
            }

            $bitsRemaining -= 8;
        }

        $canonical = inet_ntop($network);

        return $canonical === false ? null : strtolower($canonical);
    }

    /**
     * Canonical form of a single IP address (compressed lowercase IPv6).
     */
    public static function canonicalIp(string $ip): ?string
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return null;
            }

            $canonical = inet_ntop($packed);

            return $canonical === false ? null : strtolower($canonical);
        }

        return null;
    }

    public static function isCidr(string $normalized): bool
    {
        return str_contains($normalized, '/');
    }

    /**
     * Does a stored rule value (plain IP or CIDR) match the given IP?
     */
    public static function matches(string $ruleValue, string $ip): bool
    {
        $ruleValue = trim($ruleValue);
        $ip = trim($ip);

        if ($ruleValue === '' || $ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (! str_contains($ruleValue, '/')) {
            $canonicalRule = self::canonicalIp($ruleValue);
            $canonicalIp = self::canonicalIp($ip);

            if ($canonicalRule === null || $canonicalIp === null) {
                return false;
            }

            return strtolower($canonicalRule) === strtolower($canonicalIp);
        }

        [$base, $prefix] = explode('/', $ruleValue, 2) + [null, null];

        if ($base === null || $prefix === null || ! ctype_digit(trim($prefix))) {
            return false;
        }

        $base = trim($base);
        $prefixLength = (int) trim($prefix);

        $isV4Rule = filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6Rule = filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isV4Ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6Ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        // Families must match; no v4-mapped comparisons.
        if (($isV4Rule && ! $isV4Ip) || ($isV6Rule && ! $isV6Ip)) {
            return false;
        }

        if ($isV4Rule && ($prefixLength < 0 || $prefixLength > 32)) {
            return false;
        }

        if ($isV6Rule && ($prefixLength < 0 || $prefixLength > 128)) {
            return false;
        }

        $rulePacked = inet_pton($base);
        $ipPacked = inet_pton($ip);

        if ($rulePacked === false || $ipPacked === false || strlen($rulePacked) !== strlen($ipPacked)) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (substr($rulePacked, 0, $fullBytes) !== substr($ipPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits > 0) {
            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
            $ruleByte = ord($rulePacked[$fullBytes] ?? "\x00");
            $ipByte = ord($ipPacked[$fullBytes] ?? "\x00");

            if (($ruleByte & $mask) !== ($ipByte & $mask)) {
                return false;
            }
        }

        return true;
    }
}
