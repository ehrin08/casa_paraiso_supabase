<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MobileAdminStaffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_edit_and_schedule_therapists(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $staff = StaffProfile::factory()->create();
        $token = $this->token($admin);

        $this->withToken($token)->getJson('/api/v1/admin/staff')->assertOk()
            ->assertJsonPath('data.0.id', $staff->id);
        $this->withToken($token)->patchJson("/api/v1/admin/staff/{$staff->id}", [
            'name' => 'Updated Therapist',
            'email' => $staff->user->email,
            'phone' => '09170000000',
            'staff_type' => StaffProfile::TYPE_THERAPIST,
            'position' => 'Senior Therapist',
            'specialization' => 'Hilot',
            'is_active' => true,
            'is_bookable' => true,
            'service_ids' => [$service->id],
        ])->assertOk()->assertJsonPath('data.name', 'Updated Therapist');

        $schedule = $this->withToken($token)->postJson("/api/v1/admin/staff/{$staff->id}/weekly-schedules", [
            'day_of_week' => StaffWeeklySchedule::MONDAY,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'ends_next_day' => false,
            'is_available' => true,
        ])->assertCreated()->assertJsonPath('data.start_time', '13:00')->json('data');

        $this->withToken($token)->deleteJson("/api/v1/admin/staff/{$staff->id}/weekly-schedules/{$schedule['id']}")
            ->assertOk();

        $exception = $this->withToken($token)->postJson("/api/v1/admin/staff/{$staff->id}/schedule-exceptions", [
            'exception_date' => today()->addDay()->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Approved leave.',
        ])->assertCreated()->json('data');
        $this->withToken($token)->deleteJson("/api/v1/admin/staff/{$staff->id}/schedule-exceptions/{$exception['id']}")
            ->assertOk();
    }

    public function test_admin_can_build_and_publish_a_dated_weekly_roster(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $token = $this->token($admin);
        $week = today()->startOfWeek(Carbon::SUNDAY)->addWeek()->toDateString();
        $roster = $this->withToken($token)->getJson("/api/v1/admin/staff-roster?week={$week}")
            ->assertOk()->json();

        $this->withToken($token)->postJson("/api/v1/admin/staff-roster/{$roster['schedule_week_id']}/shifts", [
            'staff_profile_id' => $staff->id,
            'schedule_date' => $week,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'ends_next_day' => false,
        ])->assertOk()->assertJsonCount(1, 'draft_shifts');
        $this->withToken($token)->postJson("/api/v1/admin/staff-roster/{$roster['schedule_week_id']}/publish")
            ->assertOk()->assertJsonPath('published_at', fn ($value) => is_string($value));
    }

    public function test_regular_admin_cannot_create_staff_access(): void
    {
        $service = Service::factory()->create();
        $payload = [
            'name' => 'New Therapist',
            'email' => 'new.therapist@example.test',
            'staff_type' => StaffProfile::TYPE_THERAPIST,
            'is_active' => true,
            'is_bookable' => true,
            'service_ids' => [$service->id],
        ];
        $this->withToken($this->token(User::factory()->admin()->create()))->postJson('/api/v1/admin/staff', $payload)->assertForbidden();
    }

    public function test_protected_super_admin_can_create_staff_access(): void
    {
        $service = Service::factory()->create();
        $payload = [
            'name' => 'New Therapist',
            'email' => 'new.therapist@example.test',
            'staff_type' => StaffProfile::TYPE_THERAPIST,
            'is_active' => true,
            'is_bookable' => true,
            'service_ids' => [$service->id],
        ];

        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => config('auth.super_admin_email')]);
        $this->withToken($this->token($super))->postJson('/api/v1/admin/staff', $payload)
            ->assertCreated()->assertJsonPath('data.email', 'new.therapist@example.test');
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
