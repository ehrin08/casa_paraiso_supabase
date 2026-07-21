<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Admin\ApplicationSettingRequest;
use App\Models\ApplicationSetting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MobileAdminSettingController
{
    public function show(): JsonResponse
    {
        $settings = ApplicationSetting::current();

        return response()->json(['data' => ['settings' => ['business_name' => $settings->business_name, 'contact_email' => $settings->contact_email, 'contact_phone' => $settings->contact_phone, 'business_address' => $settings->business_address, 'location_landmarks' => $settings->location_landmarks, 'facebook_url' => $settings->facebook_url, 'messenger_url' => $settings->messenger_url, 'map_url' => $settings->map_url, 'default_payment_method' => $settings->default_payment_method], 'payment_methods' => Transaction::PAYMENT_METHODS, 'operating' => ['timezone' => config('app.timezone'), 'opens_at' => config('casa.business_hours.opens_at'), 'closes_at' => config('casa.business_hours.closes_at'), 'slot_interval_minutes' => (int) config('casa.booking.slot_interval_minutes', 30), 'commission_rate' => number_format((float) config('casa.commissions.therapist_rate', 0.22), 4, '.', '')], 'security' => $this->securityChecks()]])->header('Cache-Control', 'no-store');
    }

    public function update(ApplicationSettingRequest $request): JsonResponse
    {
        if (! ApplicationSetting::tableAvailable()) {
            throw ValidationException::withMessages(['settings' => 'The application settings migration must be applied before settings can be saved.']);
        }
        ApplicationSetting::updateCurrent([...$request->validated(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Business settings updated.'])->header('Cache-Control', 'no-store');
    }

    private function securityChecks(): array
    {
        $production = app()->environment('production');
        $trustedHosts = config('casa.security.trusted_hosts', []);

        return [
            ['label' => 'Debug mode', 'ready' => ! $production || ! config('app.debug'), 'value' => config('app.debug') ? 'Enabled' : 'Disabled'],
            ['label' => 'HTTPS cookies', 'ready' => (bool) config('session.secure'), 'value' => config('session.secure') ? 'Required' : 'Local default'],
            ['label' => 'Session encryption', 'ready' => (bool) config('session.encrypt'), 'value' => config('session.encrypt') ? 'Enabled' : 'Disabled'],
            ['label' => 'Trusted hosts', 'ready' => $trustedHosts !== [], 'value' => $trustedHosts === [] ? 'Not restricted' : (string) count($trustedHosts)],
        ];
    }
}
