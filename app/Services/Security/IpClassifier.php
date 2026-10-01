<?php

namespace App\Services\Security;

use Symfony\Component\HttpFoundation\IpUtils;

final class IpClassifier
{
    public const PUBLIC = 'public';

    public const PRIVATE = 'private';

    public const LOOPBACK = 'loopback';

    public const RESERVED = 'reserved';

    public const INVALID = 'invalid';

    public function classify(?string $ip): string
    {
        $canonical = $ip === null ? null : IpNetwork::canonicalIp($ip);

        if ($canonical === null) {
            return self::INVALID;
        }

        if (IpUtils::checkIp($canonical, ['127.0.0.0/8', '::1/128'])) {
            return self::LOOPBACK;
        }

        if (IpUtils::checkIp($canonical, [
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            'fc00::/7',
        ])) {
            return self::PRIVATE;
        }

        if (filter_var($canonical, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return self::PUBLIC;
        }

        return self::RESERVED;
    }

    public function isPublic(?string $ip): bool
    {
        return $this->classify($ip) === self::PUBLIC;
    }
}
