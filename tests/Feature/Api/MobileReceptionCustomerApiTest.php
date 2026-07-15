<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileReceptionCustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_search_and_view_customer_operational_history(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customerUser = User::factory()->customer()->create(['name' => 'Searchable Guest', 'phone' => '09170001111']);
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['name' => 'GAIA TOUCH']);
        $appointment = Appointment::factory()->for($customer)->for($service)->create();
        $transaction = Transaction::factory()->for($customer)->for($appointment)->for($service)->create();
        Feedback::factory()->for($customer)->for($appointment)->for($service)->create(['rating' => 5]);
        CustomerProfile::factory()->count(16)->create();
        $token = $this->token($receptionist);

        $this->withToken($token)->getJson('/api/v1/reception/customers?per_page=100')
            ->assertOk()->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.total', 17);

        $this->withToken($token)->getJson('/api/v1/reception/customers?q=Searchable')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $customer->id);

        $this->withToken($token)->getJson("/api/v1/reception/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Searchable Guest')
            ->assertJsonPath('data.appointments.0.id', $appointment->id)
            ->assertJsonPath('data.transactions.0.id', $transaction->id)
            ->assertJsonPath('data.feedback.0.rating', 5);
    }

    public function test_receptionist_updates_only_permitted_contact_and_operational_fields(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $originalName = $customer->user->name;
        $originalEmail = $customer->user->email;

        $this->withToken($this->token($receptionist))->patchJson("/api/v1/reception/customers/{$customer->id}", [
            'phone' => '09171234567',
            'address' => 'Updated address',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
            'notes' => 'Call before arrival.',
            'name' => 'Unauthorized name',
            'email' => 'unauthorized@example.test',
        ])->assertOk()
            ->assertJsonPath('data.phone', '09171234567')
            ->assertJsonPath('message', 'Customer contact details updated.');

        $customer->refresh();
        $this->assertSame($originalName, $customer->user->name);
        $this->assertSame($originalEmail, $customer->user->email);
        $this->assertSame('Updated address', $customer->address);
        $this->assertSame('Call before arrival.', $customer->notes);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
