<?php

namespace App\Http\Controllers;

use App\Models\{StaffAttendance, StaffAttendanceScanRequest, StaffProfile};
use App\Services\{AttendanceQr, AttendanceWorkflow};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebAttendanceController extends Controller
{
    public function staff(Request $request): View
    {
        $staff = $request->user()->staffProfile; abort_unless($staff, 403);
        $attendance = StaffAttendance::query()->with('events.recorder')->where('staff_profile_id', $staff->id)->where('attendance_date', CarbonImmutable::now('Asia/Manila')->toDateString())->first();
        $pending = StaffAttendanceScanRequest::query()->where('staff_profile_id', $staff->id)->where('status', 'pending')->latest('scanned_at')->first();
        return view('staff.attendance', compact('attendance', 'pending'));
    }

    public function submitScan(Request $request, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['payload' => ['required', 'string', 'max:4096']]); $staff = $request->user()->staffProfile; abort_unless($staff, 403);
        $scan = $workflow->requestScan($staff, $data['payload']);
        return response()->json(['message' => 'Scan submitted for on-site confirmation.', 'scan' => $this->scanData($scan)], 201);
    }

    public function station(AttendanceQr $qr): View { return view('attendance.station', ['qr' => $qr->current()]); }
    public function qr(AttendanceQr $qr): JsonResponse { return response()->json(['qr' => $qr->current()]); }
    public function pending(): JsonResponse { return response()->json(['scans' => StaffAttendanceScanRequest::query()->where('status', 'pending')->with('staffProfile.user')->oldest('scanned_at')->get()->map(fn ($scan) => $this->scanData($scan))]); }

    public function confirm(Request $request, StaffAttendanceScanRequest $scan, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:time_in,time_out']]); $workflow->confirm($scan, $data['action'], $request->user());
        return response()->json(['message' => __('Attendance :action confirmed.', ['action' => str_replace('_', ' ', $data['action'])])]);
    }

    public function reject(Request $request, StaffAttendanceScanRequest $scan): JsonResponse
    {
        abort_unless($scan->status === 'pending', 422, 'This attendance scan was already resolved.');
        $scan->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return response()->json(['message' => __('Attendance scan rejected.')]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')], 'status' => ['nullable', 'in:open,closed'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $records = StaffAttendance::query()->with(['staffProfile.user', 'events.recorder'])->when($filters['staff_profile_id'] ?? null, fn ($q, $id) => $q->where('staff_profile_id', $id))->when($filters['status'] ?? null, fn ($q, $status) => $status === 'open' ? $q->whereNull('time_out_at') : $q->whereNotNull('time_out_at'))->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))->latest('attendance_date')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();
        return view('admin.attendance.index', ['records' => $records, 'staffProfiles' => StaffProfile::query()->with('user')->get()->sortBy('user.name')->values(), 'filters' => $filters]);
    }

    public function correct(Request $request, StaffAttendance $attendance, AttendanceWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:time_in,time_out'], 'occurred_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $workflow->correct($attendance, $data['action'], CarbonImmutable::parse($data['occurred_at']), trim($data['reason']), $request->user());
        return back()->with('status', 'attendance-corrected');
    }

    private function scanData(StaffAttendanceScanRequest $scan): array { return ['id' => $scan->id, 'therapist' => $scan->staffProfile?->user?->name, 'scanned_at' => $scan->scanned_at?->toIso8601String()]; }
}
