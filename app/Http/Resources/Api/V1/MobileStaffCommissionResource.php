<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileStaffCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->commission_type,
            'status' => $this->status,
            'basis_amount' => $this->money($this->basis_amount),
            'rate' => number_format((float) $this->commission_rate, 4, '.', ''),
            'amount' => $this->money($this->commission_amount),
            'earned_at' => $this->timestamp($this->earned_at),
            'paid_at' => $this->timestamp($this->paid_at),
            'notes' => $this->notes,
            'appointment' => $this->appointment ? [
                'id' => $this->appointment->id,
                'appointment_number' => $this->appointment->appointment_number,
                'service' => $this->appointment->service?->name,
            ] : null,
            'transaction' => $this->transaction ? [
                'id' => $this->transaction->id,
                'transaction_number' => $this->transaction->transaction_number,
            ] : null,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }
}
