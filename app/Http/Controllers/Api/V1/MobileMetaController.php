<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MobilePairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MobileMetaController
{
    public function __invoke(MobilePairing $pairing): JsonResponse
    {
        if (! Str::isUuid((string) config('casa.mobile.instance_id'))) {
            return $this->unavailable();
        }

        return response()->json([
            'data' => [
                'service' => config('casa.mobile.service'),
                'api_version' => config('casa.mobile.api_version'),
                'instance_id' => config('casa.mobile.instance_id'),
                'timezone' => config('app.timezone'),
                'server_time' => now(config('app.timezone'))->toIso8601String(),
                'supported_auth' => ['password'],
                'pairing' => [
                    'protocol' => config('casa.mobile.pairing_protocol'),
                    'enabled' => $pairing->isConfigured(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PAIRING_NOT_CONFIGURED',
                'message' => 'The mobile demonstration backend is not configured.',
            ],
        ], 503)->header('Cache-Control', 'no-store');
    }
}
