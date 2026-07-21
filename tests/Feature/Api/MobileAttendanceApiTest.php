<?php

namespace Tests\Feature\Api;

use App\Models\{StaffAttendance, StaffAttendanceEvent, StaffProfile, User};
use App\Services\AttendanceQr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_scan_requires_station_confirmation_and_admin_corrections_are_audited(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-21 14:12:10', 'Asia/Manila'));
        $staff = StaffProfile::factory()->create(); $admin = User::factory()->admin()->create(); $receptionist = User::factory()->receptionist()->create();
        $payload = app(AttendanceQr::class)->current()['payload'];

        $scan = $this->withToken($this->token($staff->user))->postJson('/api/v1/staff/attendance/scans', ['payload' => $payload])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->withToken($this->token($staff->user))->postJson('/api/v1/staff/attendance/scans', ['payload' => $payload])->assertUnprocessable();
        $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/attendance-station/scans/{$scan}/confirm", ['action' => 'time_in'])
            ->assertOk()->assertJsonPath('data.status', 'open');

        $attendance = StaffAttendance::sole();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/attendance/{$attendance->id}/correct", ['action' => 'time_out', 'occurred_at' => '2026-07-21T20:00:00+08:00', 'reason' => 'Therapist reported a missed time out.'])
            ->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('staff_attendance_events', ['staff_attendance_id' => $attendance->id, 'source' => 'verified_qr', 'event_type' => 'time_in']);
        $this->assertDatabaseHas('staff_attendance_events', ['staff_attendance_id' => $attendance->id, 'source' => 'admin_correction', 'event_type' => 'time_out', 'reason' => 'Therapist reported a missed time out.']);
    }

    public function test_expired_qr_and_non_station_roles_are_rejected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-21 14:12:10', 'Asia/Manila'));
        $staff = StaffProfile::factory()->create(); $customer = User::factory()->customer()->create();
        $payload = app(AttendanceQr::class)->current()['payload']; CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-21 14:13:10', 'Asia/Manila'));
        $this->withToken($this->token($staff->user))->postJson('/api/v1/staff/attendance/scans', ['payload' => $payload])->assertUnprocessable();
        $this->withToken($this->token($customer))->getJson('/api/v1/attendance-station/qr')->assertForbidden();
    }

    private function token(User $user): string { return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken; }
}
