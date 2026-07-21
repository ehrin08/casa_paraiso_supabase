<?php

namespace Tests\Feature;

use App\Models\{StaffProfile, User};
use App\Services\AttendanceQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_only_see_their_web_attendance_workspaces(): void
    {
        $staff = StaffProfile::factory()->create(); $admin = User::factory()->admin()->create(); $receptionist = User::factory()->receptionist()->create();
        $this->actingAs($staff->user)
            ->get(route('staff.attendance.show'))
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $this->actingAs($staff->user)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('reception.attendance.station'))->assertOk();
        $this->actingAs($receptionist)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.attendance.index'))->assertOk();
    }

    public function test_therapist_can_submit_a_web_scan_and_station_can_read_queue(): void
    {
        $staff = StaffProfile::factory()->create(); $receptionist = User::factory()->receptionist()->create();
        $payload = app(AttendanceQr::class)->current()['payload'];
        $this->actingAs($staff->user)->postJson(route('staff.attendance.scan'), ['payload' => $payload])->assertCreated();
        $this->actingAs($receptionist)->getJson(route('attendance-station.pending'))->assertOk()->assertJsonCount(1, 'scans');
    }
}
