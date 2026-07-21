<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApplicationSetting;
use App\Models\Addon;
use Illuminate\Http\JsonResponse;

class MobilePublicBusinessProfileController
{
    public function __invoke(): JsonResponse
    {
        $settings = ApplicationSetting::current();

        return response()->json([
            'data' => [
                'business_name' => $settings->business_name,
                'business_address' => $settings->business_address,
                'location_landmarks' => $settings->location_landmarks,
                'contact_email' => $settings->contact_email,
                'contact_phone' => $settings->contact_phone,
                'facebook_url' => $settings->facebook_url,
                'messenger_url' => $settings->messenger_url,
                'map_url' => $settings->map_url,
                'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(['name', 'price'])->map(fn (Addon $addon) => [$addon->name, 'PHP '.number_format((float) $addon->price, 2)])->values(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
