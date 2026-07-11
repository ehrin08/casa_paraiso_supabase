<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_redirects_to_google_login_and_post_is_unavailable(): void
    {
        $this->get('/register')->assertRedirect(route('login', absolute: false));
        $this->post('/register', [])->assertMethodNotAllowed();
    }
}
