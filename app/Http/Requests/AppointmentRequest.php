<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_profile_id' => ['nullable', 'integer', Rule::exists('customer_profiles', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')],
            'preferred_staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')],
            'requested_start_at' => ['required', 'date', 'after:now'],
            'scheduled_start_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
