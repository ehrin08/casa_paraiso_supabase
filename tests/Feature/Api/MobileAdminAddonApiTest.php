<?php

namespace Tests\Feature\Api;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAdminAddonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_addons_and_inactive_addons_are_filtered(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/v1/admin/addons', ['name' => 'Foot Spa', 'description' => 'Warm soak.', 'duration_minutes' => 15, 'price' => 350, 'is_active' => true])->assertCreated();
        $id = $response->json('data.id');
        $this->withToken($token)->patchJson("/api/v1/admin/addons/{$id}", ['name' => 'Foot Spa Plus', 'description' => 'Updated.', 'duration_minutes' => 30, 'price' => 450, 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->withToken($token)->getJson('/api/v1/admin/addons?status=inactive')->assertOk()->assertJsonFragment(['code' => 'foot-spa']);
        $this->withToken($token)->patchJson("/api/v1/admin/addons/{$id}/toggle")->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('addons', ['id' => $id, 'code' => 'foot-spa', 'is_active' => true]);
    }

    public function test_non_admins_cannot_manage_addons(): void
    {
        $staff = User::factory()->staff()->create();
        $token = $staff->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/admin/addons')->assertForbidden();
    }
}
