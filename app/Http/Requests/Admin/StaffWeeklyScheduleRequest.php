<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StaffWeeklyScheduleRequest extends FormRequest
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
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $staff = $this->route('staff');
                $weeklySchedule = $this->route('weeklySchedule');

                if (! $staff instanceof StaffProfile) {
                    return;
                }

                $hasOverlap = StaffWeeklySchedule::query()
                    ->where('staff_profile_id', $staff->id)
                    ->where('day_of_week', (int) $this->input('day_of_week'))
                    ->when($weeklySchedule instanceof StaffWeeklySchedule, fn ($query) => $query->whereKeyNot($weeklySchedule->getKey()))
                    ->where('start_time', '<', $this->input('end_time'))
                    ->where('end_time', '>', $this->input('start_time'))
                    ->exists();

                if ($hasOverlap) {
                    $validator->errors()->add('start_time', 'This weekly shift overlaps an existing shift for the selected day.');
                }
            },
        ];
    }
}
