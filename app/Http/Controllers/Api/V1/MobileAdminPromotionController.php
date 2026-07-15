<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Admin\PromotionSettingsRequest;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAdminPromotionController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'lifecycle' => ['nullable', Rule::in(['available', 'reserved', 'used', 'dismissed', 'expired'])]]);
        $search = trim((string) ($data['q'] ?? ''));
        $lifecycle = (string) ($data['lifecycle'] ?? '');
        $suggestions = $this->activityQuery($lifecycle)->with(['customerProfile.user', 'rfmSegment', 'appointments:id,promotion_suggestion_id,status'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('suggested_offer', 'like', "%{$search}%")->orWhereHas('customerProfile.user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))->orWhereHas('rfmSegment', fn (Builder $segments) => $segments->where('name', 'like', "%{$search}%"))))
            ->latest()->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();
        $segments = RfmSegment::query()->whereNotNull('preset_key')->get()->keyBy('preset_key');
        $settings = ApplicationSetting::current();

        return response()->json([
            'data' => $suggestions->getCollection()->map(fn (PromotionSuggestion $suggestion) => $this->serialize($suggestion))->values(),
            'summary' => ['available' => $this->activityQuery('available')->count(), 'reserved' => $this->activityQuery('reserved')->count(), 'used' => $this->activityQuery('used')->count(), 'expired' => $this->activityQuery('expired')->count(), 'dismissed' => $this->activityQuery('dismissed')->count()],
            'settings' => ['promotion_voucher_validity_days' => $settings->promotion_voucher_validity_days, 'validity_options' => config('casa.customer_rewards.validity_options', [])],
            'presets' => collect(config('casa.customer_rewards.presets', []))->map(function (array $preset) use ($segments): array {
                $segment = $segments->get($preset['key']);

                return [...$preset, 'addon_code' => $segment?->addon_code, 'is_active' => (bool) $segment?->is_active];
            })->values(),
            'addons' => collect(config('casa.addons', []))->map(fn (array $addon) => ['code' => $addon['code'], 'name' => $addon['name']])->values(),
            'meta' => $this->pagination($suggestions),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(PromotionSuggestion $promotion): JsonResponse
    {
        $promotion->load(['customerProfile.user', 'rfmSegment', 'appointments.service']);

        return response()->json(['data' => $this->serialize($promotion, true)])->header('Cache-Control', 'no-store');
    }

    public function updateSettings(PromotionSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            $current = ApplicationSetting::current();
            ApplicationSetting::updateCurrent(['business_name' => $current->business_name, 'contact_email' => $current->contact_email, 'contact_phone' => $current->contact_phone, 'business_address' => $current->business_address, 'default_payment_method' => $current->default_payment_method, 'promotion_voucher_validity_days' => $data['promotion_voucher_validity_days'], 'updated_by' => $request->user()->id]);
            foreach ($data['groups'] as $key => $group) {
                RfmSegment::query()->where('preset_key', $key)->update(['addon_code' => $group['addon_code'], 'is_active' => (bool) $group['is_active']]);
            }
        });

        return response()->json(['message' => 'Customer reward settings updated.'])->header('Cache-Control', 'no-store');
    }

    public function dismiss(PromotionSuggestion $promotion): JsonResponse
    {
        DB::transaction(function () use ($promotion): void {
            $locked = PromotionSuggestion::query()->lockForUpdate()->findOrFail($promotion->id);
            if (! $locked->isAvailableVoucher()) {
                throw ValidationException::withMessages(['promotion' => 'Only an available customer reward can be dismissed.']);
            }
            $locked->forceFill(['status' => PromotionSuggestion::STATUS_DISMISSED, 'dismissed_at' => now()])->save();
        }, 3);

        return response()->json(['message' => 'Customer reward dismissed.'])->header('Cache-Control', 'no-store');
    }

    private function serialize(PromotionSuggestion $suggestion, bool $detail = false): array
    {
        $data = ['id' => $suggestion->id, 'customer' => $suggestion->customerProfile ? ['id' => $suggestion->customerProfile->id, 'name' => $suggestion->customerProfile->user?->name] : null, 'group' => $suggestion->rfmSegment?->name, 'recency_days' => $suggestion->recency_days, 'frequency_count' => $suggestion->frequency_count, 'monetary_total' => number_format((float) $suggestion->monetary_total, 2, '.', ''), 'reward' => $suggestion->addonName() ?: $suggestion->suggested_offer, 'status' => $suggestion->lifecycle(), 'expires_at' => $suggestion->expires_at?->timezone(config('app.timezone'))->toIso8601String(), 'can_dismiss' => $suggestion->isAvailableVoucher()];
        if ($detail) {
            $data['appointments'] = $suggestion->appointments->map(fn ($appointment) => ['id' => $appointment->id, 'appointment_number' => $appointment->appointment_number, 'status' => $appointment->status, 'service' => $appointment->service?->name])->values();
        }

        return $data;
    }

    private function activityQuery(string $lifecycle): Builder
    {
        $query = PromotionSuggestion::query();

        return match ($lifecycle) {
            'available' => $query->where('status', PromotionSuggestion::STATUS_SUGGESTED)->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'expired' => $query->where('status', PromotionSuggestion::STATUS_SUGGESTED)->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            'reserved' => $query->where('status', PromotionSuggestion::STATUS_APPLIED)->whereHas('appointments', fn (Builder $appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'used' => $query->where('status', PromotionSuggestion::STATUS_APPLIED)->whereDoesntHave('appointments', fn (Builder $appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'dismissed' => $query->where('status', PromotionSuggestion::STATUS_DISMISSED),
            default => $query,
        };
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
