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

class MobileReceptionTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_list_create_and_update_payments_through_shared_workflow(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => '1000.00']);
        $therapist = StaffProfile::factory()->create();
        $appointment = Appointment::factory()->for($customer)->for($service)->for($therapist, 'staffProfile')->create([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
        ]);
        $token = $this->token($receptionist);

        $transactionId = $this->withToken($token)->postJson('/api/v1/reception/transactions', [
            'customer_profile_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now()->subMinutes(30)->toIso8601String(),
            'notes' => 'Mobile front desk payment.',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('data.payment_status', Transaction::PAYMENT_PAID)
            ->json('data.id');

        $this->withToken($token)->getJson('/api/v1/reception/transactions?per_page=100')
            ->assertOk()->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.total', 1);

        $this->withToken($token)->patchJson("/api/v1/reception/transactions/{$transactionId}", [
            'customer_profile_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_REFUNDED,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now()->subMinutes(30)->toIso8601String(),
            'notes' => 'Refunded.',
        ])->assertOk()->assertJsonPath('data.payment_status', Transaction::PAYMENT_REFUNDED);

        $earning = TherapistCommission::query()->where('transaction_id', $transactionId)->sole();
        $this->assertSame('0.00', $earning->commission_amount);
        $this->assertSame($receptionist->id, Transaction::findOrFail($transactionId)->recorded_by);
    }

    public function test_received_payment_requires_method_and_date(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();

        $this->withToken($this->token($receptionist))->postJson('/api/v1/reception/transactions', [
            'customer_profile_id' => $customer->id,
            'amount' => 100,
            'payment_status' => Transaction::PAYMENT_PAID,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['payment_method', 'paid_at']]]);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
