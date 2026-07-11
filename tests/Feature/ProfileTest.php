<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_email_is_not_editable_but_name_and_phone_are(): void
    {
        $user = User::factory()->customer()->create(['email' => 'linked@example.com', 'google_id' => 'google-1']);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Name',
            'phone' => '09171234567',
            'email' => 'ignored@example.com',
        ])->assertRedirect('/profile');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'phone' => '09171234567', 'email' => 'linked@example.com']);
    }

    public function test_privileged_users_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create(['google_id' => 'google-admin']);
        $this->actingAs($admin)->withSession(['google_reauthenticated_for_deletion' => $admin->id])->delete('/profile')->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_customer_deletion_requires_google_reauthentication_marker(): void
    {
        $customer = User::factory()->customer()->create(['google_id' => 'google-customer']);
        $this->actingAs($customer)->delete('/profile')->assertForbidden();
    }
}
