<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuthActivityLogger
{
    public function record(
        Request $request,
        string $action,
        string $status,
        ?User $user = null,
        ?string $attemptedIdentity = null,
        ?string $failureReason = null,
    ): AuthenticationLog {
        $ipAddress = (string) ($request->ip() ?? '');
        $location = app(IpWhoisService::class)->lookup($ipAddress);

        $record = AuthenticationLog::create([
            'user_id' => $user?->id,
            'attempted_identity' => $attemptedIdentity,
            'ip_address' => $ipAddress,
            'country' => $location['country'] ?? null,
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'region_code' => $location['region_code'] ?? null,
            'city' => $location['city'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'postal' => $location['postal'] ?? null,
            'isp' => $location['isp'] ?? null,
            'organization' => $location['organization'] ?? null,
            'asn' => $location['asn'] ?? null,
            'timezone' => $location['timezone'] ?? null,
            'user_agent' => $request->userAgent(),
            'action' => $action,
            'status' => $status,
            'failure_reason' => $failureReason,
            'route' => '/'.$request->path(),
            'method' => $request->method(),
            'occurred_at' => now(),
        ]);

        return $record;
    }
}
