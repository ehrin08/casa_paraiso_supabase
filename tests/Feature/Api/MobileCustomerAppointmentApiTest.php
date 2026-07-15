<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCustomerAppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_only_owned_appointments_with_summary_and_fixed_pagination(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'GAIA TOUCH', 'price' => '499.00']);

        Appointment::factory()->count(16)->for($customer)->for($service)->create();
        Appointment::factory()->create();

        $response = $this->withToken($this->token($customer->user))
            ->getJson('/api/v1/customer/appointments?per_page=100');

        $response->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('data.0.service.name', 'GAIA TOUCH')
            ->assertJsonPath('data.0.service.price', '499.00');

        $this->assertSame(
            [$customer->id],
            collect($response->json('data'))->map(fn (array $appointment) => Appointment::find($appointment['id'])->customer_profile_id)->unique()->values()->all(),
        );
    }

    public function test_customer_can_view_and_cancel_an_owned_future_confirmed_appointment(): void
    {
        $customer = CustomerProfile::factory()->create();
        $appointment = Appointment::factory()->confirmed()->for($customer)->create();
        $token = $this->token($customer->user);

        $this->withToken($token)->getJson("/api/v1/customer/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.can_cancel', true);

        $this->withToken($token)->patchJson("/api/v1/customer/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CANCELLED)
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('message', 'Appointment cancelled.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_by' => $customer->user_id,
        ]);
        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'to_status' => Appointment::STATUS_CANCELLED,
            'changed_by' => $customer->user_id,
        ]);
    }

    public function test_customer_cannot_read_another_customers_appointment_or_cancel_terminal_and_past_records(): void
    {
        $customer = CustomerProfile::factory()->create();
        $other = Appointment::factory()->create();
        $completed = Appointment::factory()->for($customer)->create(['status' => Appointment::STATUS_COMPLETED]);
        $past = Appointment::factory()->for($customer)->create([
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now(),
        ]);
        $token = $this->token($customer->user);

        $this->withToken($token)->getJson("/api/v1/customer/appointments/{$other->id}")
            ->assertForbidden()->assertJsonPath('error.code', 'APPOINTMENT_FORBIDDEN');
        $this->withToken($token)->patchJson("/api/v1/customer/appointments/{$completed->id}/cancel")
            ->assertStatus(409)->assertJsonPath('error.code', 'APPOINTMENT_NOT_CANCELLABLE');
        $this->withToken($token)->patchJson("/api/v1/customer/appointments/{$past->id}/cancel")
            ->assertStatus(409)->assertJsonPath('error.code', 'APPOINTMENT_START_PASSED');
    }

    public function test_mobile_customer_routes_require_authentication_and_customer_role(): void
    {
        $this->getJson('/api/v1/customer/appointments')->assertUnauthorized();

        $staff = User::factory()->staff()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->withToken($this->token($staff))->getJson('/api/v1/customer/appointments')->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
