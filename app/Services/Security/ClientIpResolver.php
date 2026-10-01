<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

final class ClientIpResolver
{
    public function __construct(private IpClassifier $classifier) {}

    /** @return array{ip: ?string, type: string} */
    public function resolve(Request $request): array
    {
        // Request::ip() honors Laravel's configured trusted-proxy boundary.
        // Raw forwarding headers are never parsed here.
        $ip = IpNetwork::canonicalIp((string) $request->ip());

        return [
            'ip' => $ip,
            'type' => $this->classifier->classify($ip),
        ];
    }
}
