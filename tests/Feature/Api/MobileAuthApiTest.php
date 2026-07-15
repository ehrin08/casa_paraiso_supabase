<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'email' => 'mobile@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
            'email_verified_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_verified_active_user_can_create_restore_and_revoke_a_mobile_session(): void
    {
        $user = $this->user();
        $payload = ['email' => $user->email, 'password' => 'password', 'device_id' => 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811', 'device_name' => 'Casa Paraiso Android'];

        $token = $this->postJson('/api/v1/auth/login', $payload)
            ->assertOk()->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.workspace', 'customer')
            ->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // A real device sends the next request through a fresh application request.
        // Rebooting here also prevents the test client's resolved user from masking
        // the fact that the database token was revoked.
        $this->refreshApplication();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_rejects_invalid_and_ineligible_accounts(): void
    {
        $user = $this->user();
        $payload = ['email' => $user->email, 'password' => 'wrong', 'device_id' => 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811', 'device_name' => 'Casa Paraiso Android'];
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->postJson('/api/v1/auth/login', [...$payload, 'password' => 'password'])
            ->assertOk();

        $inactive = $this->user(['email' => 'inactive@example.test', 'is_active' => false]);
        $this->postJson('/api/v1/auth/login', [...$payload, 'email' => $inactive->email, 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
    }

    public function test_sensitive_identity_changes_revoke_mobile_tokens(): void
    {
        $user = $this->user();
        $token = $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
        $user->update(['role' => User::ROLE_STAFF]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
