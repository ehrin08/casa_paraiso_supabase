<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{StaffAttendance, StaffAttendanceScanRequest, StaffProfile};
use App\Services\{AttendanceQr, AttendanceWorkflow};
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAttendanceController
{
    public function qr(AttendanceQr $qr): JsonResponse
    {
        return response()->json(['data' => $qr->current()])->header('Cache-Control', 'no-store');
    }

    public function scan(Request $request, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['payload' => ['required', 'string', 'max:4096']]);
        $staff = $request->user()->staffProfile;
        abort_unless($staff, 403);
        $scan = $workflow->requestScan($staff, $data['payload']);
        return response()->json(['data' => $this->scanData($scan), 'message' => 'Scan submitted for on-site confirmation.'], 201)->header('Cache-Control', 'no-store');
    }

    public function mine(Request $request): JsonResponse
    {
        $staff = $request->user()->staffProfile; abort_unless($staff, 403);
        $date = CarbonImmutable::now('Asia/Manila')->toDateString();
        $attendance = StaffAttendance::query()->where('staff_profile_id', $staff->id)->where('attendance_date', $date)->with('events.recorder')->first();
        $pending = StaffAttendanceScanRequest::query()->where('staff_profile_id', $staff->id)->where('status', 'pending')->latest('scanned_at')->first();
        return response()->json(['data' => ['attendance' => $attendance ? $this->attendanceData($attendance) : null, 'pending_scan' => $pending ? $this->scanData($pending) : null]])->header('Cache-Control', 'no-store');
    }

    public function pending(): JsonResponse
    {
        $items = StaffAttendanceScanRequest::query()->where('status', 'pending')->with('staffProfile.user')->orderBy('scanned_at')->get();
        return response()->json(['data' => $items->map(fn ($scan) => $this->scanData($scan))])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, StaffAttendanceScanRequest $scan, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:time_in,time_out']]);
        $attendance = $workflow->confirm($scan, $data['action'], $request->user());
        return response()->json(['data' => $this->attendanceData($attendance), 'message' => 'Attendance '.$data['action'].' confirmed.'])->header('Cache-Control', 'no-store');
    }

    public function reject(Request $request, StaffAttendanceScanRequest $scan): JsonResponse
    {
        abort_unless($scan->status === 'pending', 422, 'This attendance scan was already resolved.');
        $scan->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return response()->json(['message' => 'Attendance scan rejected.'])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'], 'status' => ['nullable', 'in:open,closed']]);
        $records = StaffAttendance::query()->with(['staffProfile.user', 'events.recorder'])->when($data['staff_profile_id'] ?? null, fn ($q, $id) => $q->where('staff_profile_id', $id))->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))->when(($data['status'] ?? null) === 'open', fn ($q) => $q->whereNull('time_out_at'))->when(($data['status'] ?? null) === 'closed', fn ($q) => $q->whereNotNull('time_out_at'))->latest('attendance_date')->paginate(config('casa.pagination.per_page', 15));
        return response()->json(['data' => collect($records->items())->map(fn ($record) => $this->attendanceData($record)), 'staff' => StaffProfile::query()->with('user')->orderBy('id')->get()->map(fn ($staff) => ['id' => $staff->id, 'name' => $staff->user?->name]), 'meta' => ['current_page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'from' => $records->firstItem(), 'to' => $records->lastItem()]])->header('Cache-Control', 'no-store');
    }

    public function correct(Request $request, StaffAttendance $attendance, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:time_in,time_out'], 'occurred_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $record = $workflow->correct($attendance, $data['action'], CarbonImmutable::parse($data['occurred_at']), trim($data['reason']), $request->user());
        return response()->json(['data' => $this->attendanceData($record), 'message' => 'Attendance correction recorded.'])->header('Cache-Control', 'no-store');
    }

    private function scanData(StaffAttendanceScanRequest $scan): array { return ['id' => $scan->id, 'status' => $scan->status, 'scanned_at' => optional($scan->scanned_at)->toIso8601String(), 'expires_at' => optional($scan->expires_at)->toIso8601String(), 'therapist' => ['id' => $scan->staff_profile_id, 'name' => $scan->staffProfile?->user?->name]]; }
    private function attendanceData(StaffAttendance $attendance): array { return ['id' => $attendance->id, 'attendance_date' => $attendance->attendance_date?->toDateString(), 'time_in_at' => optional($attendance->time_in_at)->toIso8601String(), 'time_out_at' => optional($attendance->time_out_at)->toIso8601String(), 'status' => $attendance->time_out_at ? 'closed' : 'open', 'therapist' => ['id' => $attendance->staff_profile_id, 'name' => $attendance->staffProfile?->user?->name], 'events' => $attendance->events->map(fn ($event) => ['id' => $event->id, 'action' => $event->event_type, 'source' => $event->source, 'occurred_at' => optional($event->occurred_at)->toIso8601String(), 'reason' => $event->reason, 'recorded_by' => $event->recorder?->name])->values()]; }
}
