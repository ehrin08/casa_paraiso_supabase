<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStaffAppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_dashboard_and_schedule_include_only_assigned_appointments(): void
    {
        $staff = StaffProfile::factory()->create();
        $mine = Appointment::factory()->for($staff, 'staffProfile')->create(['scheduled_start_at' => now()->setTime(14, 0)]);
        Appointment::factory()->create(['scheduled_start_at' => now()->setTime(15, 0)]);
        $token = $this->token($staff->user);

        $this->withToken($token)->getJson('/api/v1/staff/dashboard')->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.profile.id', $staff->id)
            ->assertJsonPath('data.today_appointments.0.id', $mine->id);

        $this->withToken($token)->getJson('/api/v1/staff/appointments?per_page=100')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.actions.can_edit', false)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_therapist_can_mark_only_an_assigned_confirmed_visit_no_show(): void
    {
        $staff = StaffProfile::factory()->create();
        $mine = Appointment::factory()->for($staff, 'staffProfile')->create();
        $other = Appointment::factory()->create();
        $token = $this->token($staff->user);

        $this->withToken($token)->postJson("/api/v1/staff/appointments/{$mine->id}/outcome", [
            'status' => Appointment::STATUS_NO_SHOW,
            'reason' => 'Customer did not arrive.',
        ])->assertOk()->assertJsonPath('data.status', Appointment::STATUS_NO_SHOW);

        $this->withToken($token)->postJson("/api/v1/staff/appointments/{$other->id}/outcome", [
            'status' => Appointment::STATUS_NO_SHOW,
        ])->assertForbidden();

        $this->withToken($token)->postJson("/api/v1/staff/appointments/{$mine->id}/outcome", [
            'status' => Appointment::STATUS_CANCELLED,
        ])->assertUnprocessable();
    }

    public function test_therapist_can_finish_assigned_service_and_generate_commission(): void
    {
        $staff = StaffProfile::factory()->create();
        $service = Service::factory()->create(['price' => '1000.00']);
        $customer = CustomerProfile::factory()->create();
        $appointment = Appointment::factory()->for($staff, 'staffProfile')->for($service)->for($customer)->create([
            'scheduled_start_at' => now()->subHours(2),
            'scheduled_end_at' => now()->subHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->withToken($this->token($staff->user))->postJson("/api/v1/staff/appointments/{$appointment->id}/complete", [
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->toIso8601String(),
            'notes' => 'Recorded after treatment.',
        ])->assertCreated()->assertJsonPath('data.amount', '1000.00');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_COMPLETED]);
        $this->assertDatabaseHas('transactions', ['appointment_id' => $appointment->id, 'recorded_by' => $staff->user_id]);
        $this->assertDatabaseHas('therapist_commissions', ['staff_profile_id' => $staff->id, 'status' => TherapistCommission::STATUS_PENDING, 'commission_amount' => '220.00']);
    }

    public function test_staff_routes_reject_other_roles_and_guests(): void
    {
        $this->getJson('/api/v1/staff/dashboard')->assertUnauthorized();
        $customer = User::factory()->customer()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->withToken($this->token($customer))->getJson('/api/v1/staff/dashboard')->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
