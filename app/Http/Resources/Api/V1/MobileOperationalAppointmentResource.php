<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileOperationalAppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $transaction = $this->relationLoaded('latestTransaction')
            ? $this->latestTransaction
            : $this->transactions->sortByDesc('id')->first();
        $start = $this->scheduled_start_at ?? $this->requested_start_at;
        $isTherapist = $request->user()?->role === 'staff';

        return [
            'id' => $this->id,
            'appointment_number' => $this->appointment_number,
            'status' => $this->status,
            'starts_at' => $this->timestamp($start),
            'ends_at' => $this->timestamp($this->scheduled_end_at),
            'customer_notes' => $this->customer_notes,
            'internal_notes' => $this->internal_notes,
            'customer' => $this->customerProfile ? [
                'id' => $this->customerProfile->id,
                'customer_code' => $this->customerProfile->customer_code,
                'name' => $this->customerProfile->user?->name,
                'phone' => $this->customerProfile->user?->phone,
            ] : null,
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
            'expected_amount' => $this->money($this->expectedAmount()),
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'amount' => $this->money($transaction->amount),
                'payment_status' => $transaction->payment_status,
            ] : null,
            'actions' => [
                'can_edit' => ! $isTherapist && $this->status === Appointment::STATUS_CONFIRMED,
                'can_cancel' => ! $isTherapist && $this->status === Appointment::STATUS_CONFIRMED,
                'can_mark_no_show' => $this->status === Appointment::STATUS_CONFIRMED,
                'can_finish' => $this->status === Appointment::STATUS_CONFIRMED
                    && $start?->isFuture() === false
                    && $transaction === null,
            ],
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
