<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStaffOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lookup_exposes_only_customers_served_by_therapist(): void
    {
        $staff = StaffProfile::factory()->create();
        $mine = CustomerProfile::factory()->create();
        $other = CustomerProfile::factory()->create();
        Appointment::factory()->for($staff, 'staffProfile')->for($mine)->create();
        Appointment::factory()->for($other)->create();
        $token = $this->token($staff->user);

        $this->withToken($token)->getJson('/api/v1/staff/customers')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->withToken($token)->getJson("/api/v1/staff/customers/{$mine->id}")->assertOk()
            ->assertJsonPath('data.id', $mine->id)->assertJsonCount(1, 'data.appointments');
        $this->withToken($token)->getJson("/api/v1/staff/customers/{$other->id}")->assertForbidden();
    }

    public function test_related_payments_and_feedback_are_read_only_and_scoped(): void
    {
        $staff = StaffProfile::factory()->create();
        $mine = Appointment::factory()->for($staff, 'staffProfile')->create();
        $other = Appointment::factory()->create();
        $mineTransaction = Transaction::factory()->for($mine)->create([
            'customer_profile_id' => $mine->customer_profile_id,
            'service_id' => $mine->service_id,
        ]);
        Transaction::factory()->for($other)->create();
        $mineFeedback = Feedback::factory()->for($mine)->create([
            'customer_profile_id' => $mine->customer_profile_id,
            'service_id' => $mine->service_id,
            'comment' => 'Excellent therapist.',
        ]);
        $otherFeedback = Feedback::factory()->for($other)->create();
        $token = $this->token($staff->user);

        $this->withToken($token)->getJson('/api/v1/staff/transactions')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mineTransaction->id);
        $this->withToken($token)->getJson('/api/v1/staff/feedback')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mineFeedback->id);
        $this->withToken($token)->getJson("/api/v1/staff/feedback/{$otherFeedback->id}")->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/staff/transactions', [])->assertMethodNotAllowed();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
