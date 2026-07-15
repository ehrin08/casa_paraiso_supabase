<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $start = $this->scheduled_start_at ?? $this->requested_start_at;

        return [
            'id' => $this->id,
            'appointment_number' => $this->appointment_number,
            'status' => $this->status,
            'starts_at' => $this->timestamp($start),
            'ends_at' => $this->timestamp($this->scheduled_end_at),
            'customer_notes' => $this->customer_notes,
            'can_cancel' => $this->status === 'confirmed' && $this->scheduled_start_at?->isFuture() === true,
            'can_submit_feedback' => $this->status === 'completed' && $this->feedback === null,
            'service' => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'duration_minutes' => $this->service->duration_minutes,
                'price' => $this->money($this->service->price),
            ] : null,
            'therapist' => $this->staffProfile ? [
                'id' => $this->staffProfile->id,
                'name' => $this->staffProfile->user?->name,
            ] : null,
            'preferred_therapist' => $this->preferredStaffProfile ? [
                'id' => $this->preferredStaffProfile->id,
                'name' => $this->preferredStaffProfile->user?->name,
            ] : null,
            'addons' => $this->addons->map(fn ($addon): array => [
                'code' => $addon->addon_code,
                'name' => $addon->addon_name,
                'price' => $this->money($addon->price),
                'duration_minutes' => $addon->duration_minutes,
            ])->values(),
            'voucher' => $this->promotionSuggestion ? [
                'id' => $this->promotionSuggestion->id,
                'code' => $this->promotionSuggestion->addon_code,
                'name' => $this->promotionSuggestion->addonName(),
            ] : null,
            'expected_amount' => $this->money($this->expectedAmount()),
            'feedback' => $this->feedback ? [
                'id' => $this->feedback->id,
                'rating' => $this->feedback->rating,
                'sentiment' => $this->feedback->sentiment_label,
                'submitted_at' => $this->timestamp($this->feedback->submitted_at),
            ] : null,
        ];
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
