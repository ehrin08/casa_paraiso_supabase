<?php

namespace Tests\Feature\Api;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileCustomerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_read_and_update_profile_without_editing_email(): void
    {
        $customer = CustomerProfile::factory()->create([
            'address' => 'Old address',
            'contact_preference' => CustomerProfile::CONTACT_EMAIL,
        ]);
        $user = $customer->user;

        $this->withToken($this->token($user))->getJson('/api/v1/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.address', 'Old address')
            ->assertJsonPath('data.has_password', true)
            ->assertJsonCount(3, 'data.contact_preferences');

        $this->withToken($this->token($user))->patchJson('/api/v1/customer/profile', [
            'name' => 'Updated Customer',
            'email' => 'ignored@example.test',
            'phone' => '09171234567',
            'address' => 'New address',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Customer')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.contact_preference', CustomerProfile::CONTACT_SMS)
            ->assertJsonPath('message', 'Profile updated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Customer',
            'email' => $user->email,
            'phone' => '09171234567',
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'id' => $customer->id,
            'address' => 'New address',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
        ]);
    }

    public function test_profile_validation_uses_stable_errors(): void
    {
        $customer = CustomerProfile::factory()->create();
        $token = $this->token($customer->user);

        $this->withToken($token)->patchJson('/api/v1/customer/profile', [
            'name' => '',
            'contact_preference' => 'carrier-pigeon',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['name', 'contact_preference']]]);

    }

    public function test_profile_routes_require_customer_authentication(): void
    {
        $this->getJson('/api/v1/customer/profile')->assertUnauthorized();

        $staff = User::factory()->staff()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->withToken($this->token($staff))->getJson('/api/v1/customer/profile')->assertForbidden();
    }

    public function test_mobile_user_can_change_existing_password_and_all_tokens_are_revoked(): void
    {
        $user = User::factory()->customer()->create([
            'password' => Hash::make('old-password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        CustomerProfile::factory()->for($user)->create();
        $token = $this->token($user);
        $user->createToken('android:other', ['mobile'], now()->addDays(30));

        $this->withToken($token)->patchJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'New-secure-password-123!',
            'password_confirmation' => 'New-secure-password-123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Password updated. Sign in again on this phone.');

        $this->assertTrue(Hash::check('New-secure-password-123!', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_change_rejects_wrong_current_password_without_revoking_session(): void
    {
        $customer = CustomerProfile::factory()->create();
        $token = $this->token($customer->user);

        $this->withToken($token)->patchJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'New-secure-password-123!',
            'password_confirmation' => 'New-secure-password-123!',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['current_password']]]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
