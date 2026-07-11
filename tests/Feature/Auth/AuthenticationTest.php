<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_google_only(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertDontSee('name="password"', false);
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'password'])->assertMethodNotAllowed();
    }

    public function test_unknown_verified_google_user_becomes_customer(): void
    {
        $this->mockGoogle('google-1', 'New Guest', 'Guest@Example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('customer.appointments.index', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'guest@example.com', 'role' => User::ROLE_CUSTOMER, 'google_id' => 'google-1']);
        $this->assertDatabaseCount('customer_profiles', 1);
    }

    public function test_designated_email_becomes_the_protected_super_admin(): void
    {
        $this->mockGoogle('google-root', 'Ehrin John', 'EHRINJOHN08@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'ehrinjohn08@gmail.com', 'role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_preprovisioned_role_is_preserved_and_linked(): void
    {
        $user = User::factory()->staff()->create(['email' => 'staff@example.com', 'google_id' => null]);
        $this->mockGoogle('staff-google', 'Staff Name', 'staff@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('staff.dashboard', absolute: false));
        $this->assertSame(User::ROLE_STAFF, $user->fresh()->role);
        $this->assertSame('staff-google', $user->fresh()->google_id);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->mockGoogle('google-2', 'Guest', 'guest@example.com', false);
        $this->get('/auth/google/callback')->assertRedirect(route('login', absolute: false))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    private function mockGoogle(string $id, string $name, string $email, bool $verified = true): void
    {
        $googleUser = new GoogleUser();
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->user = ['verified_email' => $verified];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
