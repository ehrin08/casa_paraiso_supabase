<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_super_admin_can_manage_user_access(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => config('auth.super_admin_email')]);
        $token = $this->token($super);

        $created = $this->withToken($token)->postJson('/api/v1/admin/users', [
            'name' => 'Mobile Reception',
            'email' => 'mobile.reception@example.test',
            'role' => User::ROLE_RECEPTIONIST,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.role', User::ROLE_RECEPTIONIST)->json('data');

        $this->withToken($token)->patchJson("/api/v1/admin/users/{$created['id']}", [
            'name' => 'Mobile Customer',
            'email' => 'mobile.reception@example.test',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.role', User::ROLE_CUSTOMER)
            ->assertJsonPath('data.customer_profile_id', fn ($value) => is_int($value));
        $this->withToken($token)->getJson('/api/v1/admin/users')->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_regular_admin_cannot_manage_user_access(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withToken($this->token($admin))->getJson('/api/v1/admin/users')->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
