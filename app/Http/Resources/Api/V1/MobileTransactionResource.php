<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->timestamp($this->paid_at),
            'notes' => $this->notes,
            'customer' => $this->customerProfile ? [
                'id' => $this->customerProfile->id,
                'customer_code' => $this->customerProfile->customer_code,
                'name' => $this->customerProfile->user?->name,
            ] : null,
            'service' => $this->service ? ['id' => $this->service->id, 'name' => $this->service->name] : null,
            'appointment' => $this->appointment ? [
                'id' => $this->appointment->id,
                'appointment_number' => $this->appointment->appointment_number,
            ] : null,
            'recorded_by' => $this->recorder?->name,
        ];
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }
}
