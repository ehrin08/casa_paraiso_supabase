<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAdminOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_and_operational_routes_are_available_to_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        Appointment::factory()->for($customer)->create();
        $token = $this->token($admin);

        $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('X-Request-ID')
            ->assertHeaderContains('Server-Timing', 'app;dur=')
            ->assertJsonPath('data.summary.customers', 1);
        $this->withToken($token)->getJson('/api/v1/admin/appointments')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/customers')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/transactions')->assertOk();

    }

    public function test_admin_routes_reject_therapists(): void
    {
        $staff = User::factory()->staff()->create();

        $this->withToken($this->token($staff))->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_admin_routes_reject_guests(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_update_customer_operational_details(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();

        $this->withToken($this->token($admin))->patchJson("/api/v1/admin/customers/{$customer->id}", [
            'phone' => '09171234567',
            'address' => 'San Pablo City',
            'contact_preference' => 'sms',
            'notes' => 'Prefers a quiet room.',
        ])->assertOk()->assertJsonPath('data.phone', '09171234567');

        $this->assertDatabaseHas('customer_profiles', ['id' => $customer->id, 'address' => 'San Pablo City']);
    }

    public function test_dashboard_cache_is_private_and_is_invalidated_after_a_mobile_mutation(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        $customer = CustomerProfile::factory()->create();

        $this->withToken($token)->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.summary.customers', 1);

        CustomerProfile::factory()->create();

        $this->withToken($token)->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.summary.customers', 1);

        $this->withToken($token)->patchJson("/api/v1/admin/customers/{$customer->id}", [
            'phone' => '09170000000',
            'address' => 'San Pablo City',
            'contact_preference' => 'sms',
            'notes' => 'Cache invalidation check.',
        ])->assertOk();

        $this->withToken($token)->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.summary.customers', 2);
    }

    public function test_admin_can_manage_service_catalog_with_fixed_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);

        $created = $this->withToken($token)->postJson('/api/v1/admin/services', [
            'name' => 'Casa Restore',
            'description' => 'A restorative massage.',
            'duration_minutes' => 60,
            'price' => 950,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.price', '950.00')->json('data');

        $this->withToken($token)->patchJson("/api/v1/admin/services/{$created['id']}", [
            'name' => 'Casa Restore Plus',
            'description' => 'A longer restorative massage.',
            'duration_minutes' => 90,
            'price' => 1250,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.duration_minutes', 90);

        $this->withToken($token)->patchJson("/api/v1/admin/services/{$created['id']}/toggle")
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->withToken($token)->getJson('/api/v1/admin/services?per_page=100')->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
