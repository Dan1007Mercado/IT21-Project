<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use Illuminate\Http\JsonResponse;

class SecurityBlocklistController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rules = BlockedIp::query()->enforcing()->where('action', BlockedIp::ACTION_BLOCK)
            ->orderByDesc('blocked_at')->get(['ip_address', 'expires_at'])->map(fn (BlockedIp $rule) => [
                    'ip_address' => $rule->ip_address,
                    'expires_at' => $rule->expires_at?->toIso8601String(),
                ])->values();

        return response()->json([
            'data' => $rules,
            'policy_version' => hash('sha256', $rules->toJson()),
            'generated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
