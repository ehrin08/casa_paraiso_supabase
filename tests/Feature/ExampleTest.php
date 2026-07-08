<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('data-page-loading', false)
            ->assertSee('data-prefetch', false)
            ->assertSee('GAIA TOUCH')
            ->assertSee('AURORA BREEZE')
            ->assertSee('PHP 499.00')
            ->assertSee('PHP 849.00')
            ->assertSee('Ventosa')
            ->assertSee('1:00 PM to 12:00 MN')
            ->assertSee('Reserve your spot. You deserve this.');
    }
}
