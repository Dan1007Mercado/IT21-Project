<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuthActivityLogger
{
    public function record(
        Request $request,
        string $action,
        string $status,
        ?User $user = null,
        ?string $attemptedIdentity = null,
        ?string $failureReason = null,
        bool $withIpIntelligence = true,
    ): AuthenticationLog {
        $ipAddress = (string) ($request->ip() ?? '');
        $location = $withIpIntelligence
            ? app(IpWhoisService::class)->lookup($ipAddress)
            : null;
        $userAgentAnalysis = app(UserAgentClassifier::class)->analyze($request->userAgent());

        $payload = [
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
            'user_agent' => $userAgentAnalysis['user_agent'],
            'action' => $action,
            'status' => $status,
            'failure_reason' => $failureReason,
            'route' => '/'.$request->path(),
            'method' => $request->method(),
            'occurred_at' => now(),
        ];

        foreach ([
            'device_type' => $userAgentAnalysis['device_type'],
            'device_manufacturer' => $userAgentAnalysis['device_manufacturer'],
            'device_model' => $userAgentAnalysis['device_model'],
            'os_name' => $userAgentAnalysis['os_name'],
            'os_version' => $userAgentAnalysis['os_version'],
            'browser_name' => $userAgentAnalysis['browser_name'],
            'browser_version' => $userAgentAnalysis['browser_version'],
        ] as $column => $value) {
            if (Schema::hasColumn('authentication_logs', $column)) {
                $payload[$column] = $value;
            }
        }

        $record = AuthenticationLog::create($payload);

        return $record;
    }
}
