<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileReceptionAppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_dashboard_and_appointment_list_use_operational_contracts(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $today = Appointment::factory()->create(['scheduled_start_at' => now()->setTime(14, 0)]);
        Appointment::factory()->create();
        $token = $this->token($receptionist);

        $this->withToken($token)->getJson('/api/v1/reception/dashboard')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.summary.today', 1)
            ->assertJsonPath('data.today_appointments.0.id', $today->id)
            ->assertJsonStructure(['data' => ['today_appointments' => [['customer', 'service', 'therapist', 'actions']]]]);

        $this->withToken($token)->getJson('/api/v1/reception/appointments?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_receptionist_can_find_therapists_create_and_reschedule_confirmed_booking(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'price' => '499.00']);
        $therapist = StaffProfile::factory()->create();
        $therapist->services()->attach($service);
        $start = now('Asia/Manila')->addDays(8)->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($therapist)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '19:00:00',
            'ends_next_day' => false,
            'is_available' => true,
        ]);
        $token = $this->token($receptionist);

        $this->withToken($token)->getJson('/api/v1/reception/available-therapists?'.http_build_query([
            'service_id' => $service->id,
            'starts_at' => $start->toIso8601String(),
        ]))->assertOk()->assertJsonPath('data.0.id', $therapist->id);

        $appointmentId = $this->withToken($token)->postJson('/api/v1/reception/appointments', [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $therapist->id,
            'scheduled_start_at' => $start->toIso8601String(),
            'status' => Appointment::STATUS_CONFIRMED,
            'addon_codes' => ['hot-compress'],
            'internal_notes' => 'Front desk booking.',
        ])->assertCreated()
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED)
            ->assertJsonPath('data.addons.0.code', 'hot-compress')
            ->assertJsonPath('data.expected_amount', '699.00')
            ->json('data.id');

        $newStart = $start->copy()->addHour();
        $this->withToken($token)->patchJson("/api/v1/reception/appointments/{$appointmentId}", [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $therapist->id,
            'scheduled_start_at' => $newStart->toIso8601String(),
            'status' => Appointment::STATUS_CONFIRMED,
            'addon_codes' => ['hot-compress'],
            'internal_notes' => 'Rescheduled by front desk.',
        ])->assertOk()
            ->assertJsonPath('data.starts_at', $newStart->toIso8601String())
            ->assertJsonPath('message', 'Appointment updated.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'created_by' => $receptionist->id,
            'updated_by' => $receptionist->id,
        ]);
    }

    public function test_receptionist_can_record_outcome_and_finish_service_with_payment_side_effects(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => '1000.00']);
        $therapist = StaffProfile::factory()->create();
        $cancelled = Appointment::factory()->for($customer)->for($service)->for($therapist, 'staffProfile')->create();
        $finishable = Appointment::factory()->for($customer)->for($service)->for($therapist, 'staffProfile')->create([
            'scheduled_start_at' => now()->subHours(2),
            'scheduled_end_at' => now()->subHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $token = $this->token($receptionist);

        $this->withToken($token)->postJson("/api/v1/reception/appointments/{$cancelled->id}/outcome", [
            'status' => Appointment::STATUS_CANCELLED,
            'reason' => 'Customer called.',
        ])->assertOk()->assertJsonPath('data.status', Appointment::STATUS_CANCELLED);

        $this->withToken($token)->postJson("/api/v1/reception/appointments/{$finishable->id}/complete", [
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->subMinutes(30)->toIso8601String(),
            'notes' => 'Paid at front desk.',
        ])->assertCreated()
            ->assertJsonPath('data.payment_status', Transaction::PAYMENT_PAID)
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('message', 'Service finished and payment recorded.');

        $this->assertDatabaseHas('appointments', ['id' => $finishable->id, 'status' => Appointment::STATUS_COMPLETED]);
        $this->assertDatabaseHas('transactions', ['appointment_id' => $finishable->id, 'recorded_by' => $receptionist->id]);
        $this->assertDatabaseHas('therapist_commissions', [
            'transaction_id' => $finishable->transactions()->sole()->id,
            'status' => TherapistCommission::STATUS_PENDING,
            'commission_amount' => '220.00',
        ]);
    }

    public function test_reception_routes_reject_other_roles_and_guests(): void
    {
        $this->getJson('/api/v1/reception/dashboard')->assertUnauthorized();

        $customer = User::factory()->customer()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->withToken($this->token($customer))->getJson('/api/v1/reception/dashboard')->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
