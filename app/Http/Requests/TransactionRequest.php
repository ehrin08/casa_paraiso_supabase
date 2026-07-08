<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isStaff();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staffTransaction = $this->user()?->isStaff() ?? false;

        return [
            'customer_profile_id' => [$staffTransaction ? 'nullable' : 'required', 'integer', Rule::exists('customer_profiles', 'id')],
            'appointment_id' => [$staffTransaction ? 'required' : 'nullable', 'integer', Rule::exists('appointments', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in(Transaction::PAYMENT_STATUSES)],
            'payment_method' => ['nullable', Rule::in(Transaction::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
