<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrudIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_integrity_audit_passes_for_consistent_records(): void
    {
        Appointment::factory()->confirmed()->create();

        $this->artisan('casa:audit-crud-integrity')
            ->assertSuccessful();
    }

    public function test_integrity_audit_fails_without_changing_orphaned_records(): void
    {
        $appointment = Appointment::factory()->confirmed()->create();
        $staffProfileId = $appointment->staff_profile_id;

        $appointment->staffProfile()->firstOrFail()->delete();

        $this->artisan('casa:audit-crud-integrity')
            ->expectsOutputToContain('Active appointments assigned to deleted or missing staff: 1')
            ->assertFailed();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_profile_id' => $staffProfileId,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_approved_reference_repair_has_a_safe_dry_run_and_atomic_execution(): void
    {
        Carbon::setTestNow('2026-07-12 11:00:00');

        $actor = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $deletedStaff = StaffProfile::factory()->create();
        $receivingStaff = StaffProfile::factory()->create(['is_bookable' => true]);
        $receivingStaff->services()->attach($service);
        StaffWeeklySchedule::factory()->for($receivingStaff)->create([
            'day_of_week' => StaffWeeklySchedule::SUNDAY,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
            'is_available' => true,
        ]);

        $confirmed = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($deletedStaff, 'staffProfile')
            ->create([
                'appointment_number' => 'APT-REPAIR-CONFIRMED',
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => '2026-07-12 15:00:00',
                'scheduled_end_at' => '2026-07-12 16:00:00',
                'confirmed_at' => now()->subDay(),
            ]);
        $pending = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($deletedStaff, 'preferredStaffProfile')
            ->create([
                'appointment_number' => 'APT-REPAIR-PENDING',
                'status' => Appointment::STATUS_PENDING,
                'staff_profile_id' => null,
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
            ]);
        $deletedStaff->delete();

        $options = [
            '--actor' => $actor->id,
            '--confirmed' => $confirmed->appointment_number,
            '--staff' => $receivingStaff->id,
            '--pending' => $pending->appointment_number,
        ];

        $this->artisan('casa:repair-approved-appointment-references', $options)
            ->expectsOutputToContain('Dry run passed. No data was changed.')
            ->assertSuccessful();

        $this->assertSame($deletedStaff->id, $confirmed->fresh()->staff_profile_id);
        $this->assertSame($deletedStaff->id, $pending->fresh()->preferred_staff_profile_id);

        $this->artisan('casa:repair-approved-appointment-references', [
            ...$options,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame($receivingStaff->id, $confirmed->fresh()->staff_profile_id);
        $this->assertNull($pending->fresh()->preferred_staff_profile_id);
        $this->artisan('casa:audit-crud-integrity')->assertSuccessful();
    }
}
