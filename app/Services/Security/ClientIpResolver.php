<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

final class ClientIpResolver
{
    public function __construct(private IpClassifier $classifier) {}

    /** @return array{ip: ?string, type: string} */
    public function resolve(Request $request): array
    {
        $remoteAddress = $request->server('REMOTE_ADDR');
        $cfIp = trim((string) $request->header('CF-Connecting-IP'));

        if (in_array($remoteAddress, ['127.0.0.1', '::1'], true) && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            $ip = IpNetwork::canonicalIp($cfIp);
        } else {
            // Request::ip() honors Laravel's configured trusted-proxy boundary.
            $ip = IpNetwork::canonicalIp((string) $request->ip());
        }

        return [
            'ip' => $ip,
            'type' => $this->classifier->classify($ip),
        ];
    }
}
