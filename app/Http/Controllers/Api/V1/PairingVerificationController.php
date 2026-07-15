<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MobilePairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PairingVerificationController
{
    public function __invoke(Request $request, MobilePairing $pairing): JsonResponse
    {
        if (! $pairing->isConfigured()) {
            return $this->error('PAIRING_NOT_CONFIGURED', 'The mobile demonstration backend is not ready for pairing.', 503);
        }

        $code = (string) $request->input('code', '');
        $instanceId = (string) $request->input('instance_id', '');

        if (! preg_match('/^\d{8}$/', $code) || ! Str::isUuid($instanceId)) {
            return $this->error('PAIRING_CODE_INVALID_OR_EXPIRED', 'The pairing code is invalid, expired, or has already been used.', 422);
        }

        if (! hash_equals((string) config('casa.mobile.instance_id'), $instanceId)
            || ! hash_equals(strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)), strtolower($request->getHost()))) {
            return $this->error('PAIRING_CONTEXT_MISMATCH', 'The pairing code does not belong to this server.', 409);
        }

        if (! $pairing->consume($code, $instanceId, $request->getHost())) {
            return $this->error('PAIRING_CODE_INVALID_OR_EXPIRED', 'The pairing code is invalid, expired, or has already been used.', 422);
        }

        return response()->json([
            'data' => [
                'instance_id' => $instanceId,
                'pairing_protocol' => config('casa.mobile.pairing_protocol'),
                'paired_at' => now(config('app.timezone'))->toIso8601String(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => compact('code', 'message'),
        ], $status)->header('Cache-Control', 'no-store');
    }
}
