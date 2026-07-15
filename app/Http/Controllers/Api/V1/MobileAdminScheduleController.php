<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Requests\Admin\StaffScheduleExceptionRequest;
use App\Http\Requests\Admin\StaffWeeklyScheduleRequest;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileAdminScheduleController
{
    public function storeWeekly(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): JsonResponse
    {
        try {
            $schedule = DB::transaction(function () use ($request, $staff, $guard): StaffWeeklySchedule {
                $schedule = $staff->weeklySchedules()->create([...$request->validated(), 'ends_next_day' => $request->boolean('ends_next_day'), 'is_available' => $request->boolean('is_available')]);
                $guard->assertFutureAppointmentsRemainCovered($staff);

                return $schedule;
            });
        } catch (StaffScheduleConflictException $exception) {
            $this->throwConflict($exception);
        }

        return response()->json(['data' => $this->weekly($schedule), 'message' => 'Weekly shift created.'], 201)->header('Cache-Control', 'no-store');
    }

    public function updateWeekly(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): JsonResponse
    {
        $this->assertWeeklyOwner($staff, $weeklySchedule);
        try {
            DB::transaction(function () use ($request, $staff, $weeklySchedule, $guard): void {
                $weeklySchedule->update([...$request->validated(), 'ends_next_day' => $request->boolean('ends_next_day'), 'is_available' => $request->boolean('is_available')]);
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            $this->throwConflict($exception);
        }

        return response()->json(['data' => $this->weekly($weeklySchedule->refresh()), 'message' => 'Weekly shift updated.'])->header('Cache-Control', 'no-store');
    }

    public function destroyWeekly(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): JsonResponse
    {
        $this->assertWeeklyOwner($staff, $weeklySchedule);
        try {
            DB::transaction(function () use ($staff, $weeklySchedule, $guard): void {
                $weeklySchedule->delete();
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            $this->throwConflict($exception);
        }

        return response()->json(['message' => 'Weekly shift deleted.'])->header('Cache-Control', 'no-store');
    }

    public function storeException(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): JsonResponse
    {
        try {
            $exception = DB::transaction(function () use ($request, $staff, $guard): StaffScheduleException {
                $exception = $staff->scheduleExceptions()->create([...$this->exceptionData($request), 'created_by' => $request->user()->id]);
                $guard->assertFutureAppointmentsRemainCovered($staff);

                return $exception;
            });
        } catch (StaffScheduleConflictException $conflict) {
            $this->throwConflict($conflict);
        }

        return response()->json(['data' => $this->exception($exception), 'message' => 'Schedule exception created.'], 201)->header('Cache-Control', 'no-store');
    }

    public function updateException(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): JsonResponse
    {
        $this->assertExceptionOwner($staff, $scheduleException);
        try {
            DB::transaction(function () use ($request, $staff, $scheduleException, $guard): void {
                $scheduleException->update($this->exceptionData($request));
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $conflict) {
            $this->throwConflict($conflict);
        }

        return response()->json(['data' => $this->exception($scheduleException->refresh()), 'message' => 'Schedule exception updated.'])->header('Cache-Control', 'no-store');
    }

    public function destroyException(StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): JsonResponse
    {
        $this->assertExceptionOwner($staff, $scheduleException);
        try {
            DB::transaction(function () use ($staff, $scheduleException, $guard): void {
                $scheduleException->delete();
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $conflict) {
            $this->throwConflict($conflict);
        }

        return response()->json(['message' => 'Schedule exception deleted.'])->header('Cache-Control', 'no-store');
    }

    private function exceptionData(StaffScheduleExceptionRequest $request): array
    {
        $data = $request->validated();
        if ($data['exception_type'] === StaffScheduleException::TYPE_UNAVAILABLE && empty($data['start_time']) && empty($data['end_time'])) {
            return [...$data, 'start_time' => null, 'end_time' => null, 'ends_next_day' => false];
        }

        return [...$data, 'ends_next_day' => $request->boolean('ends_next_day')];
    }

    private function weekly(StaffWeeklySchedule $schedule): array
    {
        return ['id' => $schedule->id, 'day_of_week' => $schedule->day_of_week, 'start_time' => substr((string) $schedule->start_time, 0, 5), 'end_time' => substr((string) $schedule->end_time, 0, 5), 'ends_next_day' => (bool) $schedule->ends_next_day, 'is_available' => (bool) $schedule->is_available];
    }

    private function exception(StaffScheduleException $exception): array
    {
        return ['id' => $exception->id, 'exception_date' => $exception->exception_date?->toDateString(), 'exception_type' => $exception->exception_type, 'start_time' => $exception->start_time ? substr((string) $exception->start_time, 0, 5) : null, 'end_time' => $exception->end_time ? substr((string) $exception->end_time, 0, 5) : null, 'ends_next_day' => (bool) $exception->ends_next_day, 'reason' => $exception->reason];
    }

    private function assertWeeklyOwner(StaffProfile $staff, StaffWeeklySchedule $schedule): void
    {
        abort_unless((int) $schedule->staff_profile_id === (int) $staff->id, 404);
    }

    private function assertExceptionOwner(StaffProfile $staff, StaffScheduleException $exception): void
    {
        abort_unless((int) $exception->staff_profile_id === (int) $staff->id, 404);
    }

    private function throwConflict(StaffScheduleConflictException $exception): never
    {
        $number = $exception->conflicts[0]['appointment_number'] ?? null;
        throw ValidationException::withMessages(['schedule' => $number ? "Change blocked by confirmed appointment {$number}." : 'Change blocked by a confirmed appointment.']);
    }
}
