<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileAppointmentResource;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\AppointmentAddons;
use App\Services\AppointmentAvailability;
use App\Services\AppointmentWorkflow;
use App\Services\RfmAddonVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileCustomerBookingController
{
    public function options(
        Request $request,
        AppointmentAddons $appointmentAddons,
        RfmAddonVoucher $addonVouchers,
    ): JsonResponse {
        $customer = $request->user()->customerProfile;

        if (! $customer) {
            return $this->missingCustomerProfile();
        }

        $services = Service::query()
            ->where('is_active', true)
            ->with(['staffProfiles' => fn ($query) => $query
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->with('user')])
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price' => $this->money($service->price),
                'therapists' => $service->staffProfiles
                    ->sortBy(fn (StaffProfile $staff) => $staff->user?->name)
                    ->map(fn (StaffProfile $staff): array => [
                        'id' => $staff->id,
                        'name' => $staff->user?->name,
                    ])->values(),
            ])->values();

        $addons = $appointmentAddons->catalog()->map(fn (array $addon): array => [
            ...$addon,
            'price' => $this->money($addon['price']),
        ])->values();

        $vouchers = $addonVouchers->availableFor($customer)->map(fn ($voucher): array => [
            'id' => $voucher->id,
            'code' => $voucher->addon_code,
            'name' => $voucher->addonName(),
            'expires_at' => $this->timestamp($voucher->expires_at),
        ])->values();

        return response()->json([
            'data' => [
                'services' => $services,
                'addons' => $addons,
                'vouchers' => $vouchers,
                'booking_window' => [
                    'timezone' => config('casa.business_hours.timezone', config('app.timezone')),
                    'opens_at' => config('casa.business_hours.opens_at'),
                    'closes_at' => config('casa.business_hours.closes_at'),
                    'slot_interval_minutes' => (int) config('casa.business_hours.slot_interval_minutes', 30),
                    'lead_time_minutes' => (int) config('casa.business_hours.customer_booking_lead_time_minutes', 30),
                    'initial_month' => now(config('app.timezone'))->format('Y-m'),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function availability(
        Request $request,
        AppointmentAvailability $availability,
        AppointmentAddons $appointmentAddons,
        AppointmentWorkflow $workflow,
        RfmAddonVoucher $addonVouchers,
    ): JsonResponse {
        $customer = $request->user()->customerProfile;

        if (! $customer) {
            return $this->missingCustomerProfile();
        }

        $data = $request->validate($this->bookingRules(includeStart: false));
        $service = Service::query()->where('is_active', true)->findOrFail($data['service_id']);
        $preferredStaffId = ! empty($data['preferred_staff_profile_id'])
            ? (int) $data['preferred_staff_profile_id']
            : null;
        $this->assertEligiblePreference($preferredStaffId, $service, $workflow);

        $addonCodes = $data['addon_codes'] ?? [];
        $paidAddons = $appointmentAddons->selected($addonCodes);
        $voucher = ! empty($data['promotion_suggestion_id'])
            ? $addonVouchers->availableFor($customer)->firstWhere('id', (int) $data['promotion_suggestion_id'])
            : null;

        if (! empty($data['promotion_suggestion_id']) && ! $voucher) {
            throw ValidationException::withMessages([
                'promotion_suggestion_id' => __('This add-on voucher is no longer available.'),
            ]);
        }

        $appointmentAddons->assertDoesNotDuplicateVoucher($paidAddons, $voucher);
        $scheduleAddonCodes = [...$addonCodes, ...($voucher?->addon_code ? [$voucher->addon_code] : [])];
        $result = $availability->month($service, $data['month'], $preferredStaffId, $scheduleAddonCodes);
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $result['dates'] = collect($result['dates'])->map(fn (array $slots): array => collect($slots)
            ->map(fn (array $slot): array => [
                ...$slot,
                'starts_at' => Carbon::createFromFormat('Y-m-d H:i:s', $slot['starts_at'], $timezone)->toIso8601String(),
                'ends_at' => Carbon::createFromFormat('Y-m-d H:i:s', $slot['ends_at'], $timezone)->toIso8601String(),
            ])->all())->all();

        return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
    }

    public function store(
        Request $request,
        AppointmentWorkflow $workflow,
    ): JsonResponse {
        $customer = $request->user()->customerProfile;

        if (! $customer) {
            return $this->missingCustomerProfile();
        }

        $data = $request->validate($this->bookingRules(includeStart: true));
        $service = Service::query()->where('is_active', true)->findOrFail($data['service_id']);
        $preferredStaffId = ! empty($data['preferred_staff_profile_id'])
            ? (int) $data['preferred_staff_profile_id']
            : null;
        $this->assertEligiblePreference($preferredStaffId, $service, $workflow);
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $start = Carbon::parse($data['requested_start_at'])->timezone($timezone);

        $appointment = $workflow->autoBook([
            'customer_profile_id' => $customer->id,
            'promotion_suggestion_id' => ! empty($data['promotion_suggestion_id'])
                ? (int) $data['promotion_suggestion_id']
                : null,
            'addon_codes' => $data['addon_codes'] ?? [],
            'customer_notes' => filled($data['customer_notes'] ?? null)
                ? trim((string) $data['customer_notes'])
                : null,
            'created_by' => $request->user()->id,
        ], $service, $start, $preferredStaffId, $request->user()->id);

        $appointment->load(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback']);

        return response()->json([
            'data' => (new MobileAppointmentResource($appointment))->resolve($request),
            'message' => 'Appointment confirmed and added to the schedule.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    /** @return array<string, array<int, mixed>> */
    private function bookingRules(bool $includeStart): array
    {
        $rules = [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'preferred_staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')->whereNull('deleted_at')],
            'promotion_suggestion_id' => ['nullable', 'integer', 'exists:promotion_suggestions,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            'month' => [$includeStart ? 'nullable' : 'required', 'date_format:Y-m'],
        ];

        if ($includeStart) {
            $rules['requested_start_at'] = ['required', 'date'];
            $rules['customer_notes'] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    private function assertEligiblePreference(?int $staffId, Service $service, AppointmentWorkflow $workflow): void
    {
        if (! $staffId) {
            return;
        }

        $staff = StaffProfile::query()->with(['user', 'services'])->findOrFail($staffId);

        if (! $workflow->isStaffEligibleForService($staff, $service)) {
            throw ValidationException::withMessages([
                'preferred_staff_profile_id' => __('Preferred therapist must be active and eligible for the selected service.'),
            ]);
        }
    }

    private function missingCustomerProfile(): JsonResponse
    {
        return response()->json(['error' => [
            'code' => 'CUSTOMER_PROFILE_REQUIRED',
            'message' => 'This account does not have a customer profile.',
        ]], 403)->header('Cache-Control', 'no-store');
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
