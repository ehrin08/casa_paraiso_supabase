<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AppointmentWorkflow
{
    public function nextAppointmentNumber(): string
    {
        $prefix = 'APT-'.now()->format('Ymd').'-';
        $sequence = 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Appointment::query()->where('appointment_number', $number)->exists());

        return $number;
    }

    public function scheduledEnd(CarbonInterface $start, Service $service): CarbonInterface
    {
        return $start->copy()->addMinutes($service->duration_minutes);
    }

    public function isStaffEligibleForService(StaffProfile $staffProfile, Service $service): bool
    {
        return $staffProfile->is_bookable
            && $staffProfile->user?->is_active
            && $staffProfile->services()->whereKey($service->id)->exists();
    }

    public function isStaffAvailable(
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?Appointment $ignoreAppointment = null,
    ): bool {
        $end ??= $this->scheduledEnd($start, $service);

        if (! $this->isStaffEligibleForService($staffProfile, $service)) {
            return false;
        }

        if (! $this->coversWorkingWindow($staffProfile, $start, $end)) {
            return false;
        }

        return ! $this->hasConfirmedOverlap($staffProfile, $start, $end, $ignoreAppointment);
    }

    public function hasConfirmedOverlap(
        StaffProfile $staffProfile,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignoreAppointment = null,
    ): bool {
        return Appointment::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->when($ignoreAppointment, fn ($query) => $query->whereKeyNot($ignoreAppointment->getKey()))
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->exists();
    }

    public function changeStatus(Appointment $appointment, string $status, ?int $changedBy = null, ?string $reason = null): Appointment
    {
        $fromStatus = $appointment->status;

        $appointment->forceFill([
            'status' => $status,
            'updated_by' => $changedBy,
        ]);

        if ($status === Appointment::STATUS_CONFIRMED) {
            $appointment->confirmed_at ??= now();
        }

        if ($status === Appointment::STATUS_COMPLETED) {
            $appointment->completed_at ??= now();
        }

        if ($status === Appointment::STATUS_CANCELLED) {
            $appointment->cancelled_at ??= now();
            $appointment->cancelled_by ??= $changedBy;
        }

        $appointment->save();

        if ($fromStatus !== $status) {
            AppointmentStatusLog::query()->create([
                'appointment_id' => $appointment->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);
        }

        return $appointment;
    }

    private function coversWorkingWindow(StaffProfile $staffProfile, CarbonInterface $start, CarbonInterface $end): bool
    {
        $date = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        $exceptions = $staffProfile->scheduleExceptions()
            ->whereDate('exception_date', $date)
            ->get();

        foreach ($exceptions->where('exception_type', StaffScheduleException::TYPE_UNAVAILABLE) as $exception) {
            if ($exception->start_time === null || $exception->end_time === null) {
                return false;
            }

            if ($this->timeRangesOverlap($startTime, $endTime, (string) $exception->start_time, (string) $exception->end_time)) {
                return false;
            }
        }

        $oneOffAvailable = $exceptions
            ->where('exception_type', StaffScheduleException::TYPE_AVAILABLE)
            ->contains(fn (StaffScheduleException $exception) => $exception->start_time
                && $exception->end_time
                && $startTime >= (string) $exception->start_time
                && $endTime <= (string) $exception->end_time);

        if ($oneOffAvailable) {
            return true;
        }

        return $staffProfile->weeklySchedules()
            ->where('day_of_week', Carbon::parse($start)->dayOfWeek)
            ->where('is_available', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->exists();
    }

    private function timeRangesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }
}
