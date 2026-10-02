<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalRequestActivityRequest;
use App\Services\Security\ExternalRequestActivityService;
use Illuminate\Http\JsonResponse;

class RequestActivityController extends Controller
{
    public function store(StoreExternalRequestActivityRequest $request, ExternalRequestActivityService $activities): JsonResponse
    {
        $result = $activities->ingest($request->validated());

        return response()->json(['data' => [
            'id' => $result['activity']->id,
            'classification' => $result['activity']->classification,
            'duplicate' => $result['duplicate'],
        ]], $result['duplicate'] ? 200 : 201);
    }
}
