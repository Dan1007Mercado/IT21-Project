<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalSecurityEventRequest;
use App\Services\Security\ExternalSecurityEventService;
use Illuminate\Http\JsonResponse;

class SecurityEventController extends Controller
{
    public function store(StoreExternalSecurityEventRequest $request, ExternalSecurityEventService $events): JsonResponse
    {
        $result = $events->ingest($request->validated());

        return response()->json([
            'data' => [
                'id' => $result['event']->id,
                'severity' => $result['event']->severity,
                'duplicate' => $result['duplicate'],
                'alert_id' => $result['alert']?->alert_id,
                'incident_id' => $result['incident']?->incident_id,
            ],
        ], $result['duplicate'] ? 200 : 201);
    }
}
