<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffWeeklyScheduleRequest;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaffWeeklyScheduleController extends Controller
{
    public function create(StaffProfile $staff): View
    {
        return view('admin.staff.weekly-schedules.create', [
            'staffProfile' => $staff->load('user'),
            'weeklySchedule' => new StaffWeeklySchedule(['is_available' => true]),
        ]);
    }

    public function store(StaffWeeklyScheduleRequest $request, StaffProfile $staff): RedirectResponse
    {
        $staff->weeklySchedules()->create([
            ...$request->validated(),
            'is_available' => $request->boolean('is_available'),
        ]);

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-created');
    }

    public function edit(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): View
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        return view('admin.staff.weekly-schedules.edit', [
            'staffProfile' => $staff->load('user'),
            'weeklySchedule' => $weeklySchedule,
        ]);
    }

    public function update(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        $weeklySchedule->update([
            ...$request->validated(),
            'is_available' => $request->boolean('is_available'),
        ]);

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-updated');
    }

    public function destroy(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        $weeklySchedule->delete();

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-deleted');
    }

    private function assertScheduleBelongsToStaff(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): void
    {
        abort_unless($weeklySchedule->staff_profile_id === $staff->id, 404);
    }
}
