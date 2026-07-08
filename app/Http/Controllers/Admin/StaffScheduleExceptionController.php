<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffScheduleExceptionRequest;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffScheduleExceptionController extends Controller
{
    public function create(StaffProfile $staff): View
    {
        return view('admin.staff.schedule-exceptions.create', [
            'staffProfile' => $staff->load('user'),
            'scheduleException' => new StaffScheduleException([
                'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            ]),
        ]);
    }

    public function store(StaffScheduleExceptionRequest $request, StaffProfile $staff): RedirectResponse
    {
        $staff->scheduleExceptions()->create([
            ...$this->exceptionData($request),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-created');
    }

    public function edit(StaffProfile $staff, StaffScheduleException $scheduleException): View
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        return view('admin.staff.schedule-exceptions.edit', [
            'staffProfile' => $staff->load('user'),
            'scheduleException' => $scheduleException,
        ]);
    }

    public function update(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleException $scheduleException): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        $scheduleException->update($this->exceptionData($request));

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-updated');
    }

    public function destroy(StaffProfile $staff, StaffScheduleException $scheduleException): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        $scheduleException->delete();

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-deleted');
    }

    private function exceptionData(Request $request): array
    {
        $data = $request->validated();

        if ($data['exception_type'] === StaffScheduleException::TYPE_UNAVAILABLE && empty($data['start_time']) && empty($data['end_time'])) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }

    private function assertExceptionBelongsToStaff(StaffProfile $staff, StaffScheduleException $scheduleException): void
    {
        abort_unless($scheduleException->staff_profile_id === $staff->id, 404);
    }
}
