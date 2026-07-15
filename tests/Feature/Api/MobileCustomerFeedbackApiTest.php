<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCustomerFeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_owned_eligible_visits_and_paginated_feedback_history(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'GAIA TOUCH']);
        $eligible = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now()->subDay(),
        ]);
        $otherEligible = Appointment::factory()->create([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now()->subDay(),
        ]);

        foreach (range(1, 16) as $index) {
            $appointment = Appointment::factory()->for($customer)->for($service)->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subDays($index + 1),
            ]);
            Feedback::factory()->for($customer)->for($appointment)->for($service)->create([
                'submitted_at' => now()->subDays($index),
            ]);
        }

        $response = $this->withToken($this->token($customer->user))
            ->getJson('/api/v1/customer/feedback?per_page=100');

        $response->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonCount(15, 'data')
            ->assertJsonCount(1, 'eligible_appointments')
            ->assertJsonPath('eligible_appointments.0.id', $eligible->id)
            ->assertJsonPath('eligible_appointments.0.service.name', 'GAIA TOUCH')
            ->assertJsonPath('summary.awaiting_feedback', 1)
            ->assertJsonPath('summary.submitted', 16)
            ->assertJsonPath('meta.per_page', 15);

        $this->assertNotSame($otherEligible->id, $response->json('eligible_appointments.0.id'));
    }

    public function test_customer_can_submit_feedback_for_an_owned_completed_visit(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
        ]);

        $this->withToken($this->token($customer->user))
            ->postJson('/api/v1/customer/feedback', [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'The room was not clean.',
            ])->assertCreated()
            ->assertJsonPath('data.appointment.id', $appointment->id)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.sentiment', Feedback::SENTIMENT_NEGATIVE)
            ->assertJsonPath('message', 'Thank you. Your feedback was submitted.');

        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $appointment->id,
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'rating' => 5,
            'sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
        ]);
    }

    public function test_feedback_rejects_other_visits_non_completed_visits_and_duplicates(): void
    {
        $customer = CustomerProfile::factory()->create();
        $other = Appointment::factory()->create(['status' => Appointment::STATUS_COMPLETED]);
        $confirmed = Appointment::factory()->for($customer)->create(['status' => Appointment::STATUS_CONFIRMED]);
        $completed = Appointment::factory()->for($customer)->create(['status' => Appointment::STATUS_COMPLETED]);
        Feedback::factory()->for($customer)->for($completed)->create(['service_id' => $completed->service_id]);
        $token = $this->token($customer->user);
        $payload = ['rating' => 4, 'comment' => 'Relaxing visit.'];

        $this->withToken($token)->postJson('/api/v1/customer/feedback', [...$payload, 'appointment_id' => $other->id])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->withToken($token)->postJson('/api/v1/customer/feedback', [...$payload, 'appointment_id' => $confirmed->id])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['appointment_id']]]);
        $this->withToken($token)->postJson('/api/v1/customer/feedback', [...$payload, 'appointment_id' => $completed->id])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['appointment_id']]]);

        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_feedback_routes_require_customer_authentication(): void
    {
        $this->getJson('/api/v1/customer/feedback')->assertUnauthorized();

        $staff = User::factory()->staff()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->withToken($this->token($staff))->getJson('/api/v1/customer/feedback')->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
