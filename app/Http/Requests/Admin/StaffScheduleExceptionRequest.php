<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffScheduleException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exception_date' => ['required', 'date'],
            'exception_type' => ['required', Rule::in(StaffScheduleException::TYPES)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $type = $this->input('exception_type');
                $startTime = $this->input('start_time');
                $endTime = $this->input('end_time');

                if ($type === StaffScheduleException::TYPE_AVAILABLE && (empty($startTime) || empty($endTime))) {
                    $validator->errors()->add('start_time', 'Available exceptions require start and end times.');
                    return;
                }

                if ((empty($startTime) && ! empty($endTime)) || (! empty($startTime) && empty($endTime))) {
                    $validator->errors()->add('start_time', 'Provide both start and end times, or leave both blank for a full-day unavailable exception.');
                    return;
                }

                if (! empty($startTime) && ! empty($endTime) && $endTime <= $startTime) {
                    $validator->errors()->add('end_time', 'The end time must be after the start time.');
                }
            },
        ];
    }
}
