<?php

namespace App\Services\Security;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuthActivityLogger
{
    public function __construct(
        private ClientIpResolver $clientIpResolver,
        private UserAgentClassifier $userAgentClassifier,
        private IpEnrichmentService $ipEnrichment,
        private AuthenticationDetectionService $authenticationDetection,
    ) {}

    public function record(
        Request $request,
        string $action,
        string $status,
        ?User $user = null,
        ?string $attemptedIdentity = null,
        ?string $failureReason = null,
        bool $withIpIntelligence = true,
    ): AuthenticationLog {
        $ipAddress = $this->clientIpResolver->resolve($request)['ip'];
        $userAgentAnalysis = $this->userAgentClassifier->analyze($request->userAgent());

        $payload = [
            'source' => config('intsec.source', 'intsec'),
            'user_id' => $user?->id,
            'attempted_identity' => $attemptedIdentity,
            'ip_address' => $ipAddress,
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
        $this->ipEnrichment->observe($ipAddress);
        $this->authenticationDetection->evaluate($record);

        return $record;
    }
}
