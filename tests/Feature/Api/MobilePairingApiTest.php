<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MobilePairingApiTest extends TestCase
{
    private const INSTANCE_ID = 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811';

    private const HOST = 'casa-paraiso-supabase-api-poc.onrender.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:pairing-test-application-key',
            'app.url' => 'https://'.self::HOST,
            'casa.mobile.pairing_enabled' => true,
            'casa.mobile.instance_id' => self::INSTANCE_ID,
        ]);
    }

    public function test_meta_exposes_a_stable_non_sensitive_contract(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.service', 'casa-paraiso-mobile-api')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.instance_id', self::INSTANCE_ID)
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.pairing.protocol', 2)
            ->assertJsonPath('data.pairing.enabled', true)
            ->assertJsonPath('data.supported_auth', ['password']);
    }

    public function test_meta_allows_both_capacitor_android_origins(): void
    {
        foreach (['https://localhost', 'capacitor://localhost'] as $origin) {
            $this->withHeader('Origin', $origin)
                ->getJson('/api/v1/meta')
                ->assertOk()
                ->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_capacitor_can_preflight_authenticated_appointment_mutations(): void
    {
        $origin = 'capacitor://localhost';

        $this->call('OPTIONS', '/api/v1/customer/appointments/1/cancel', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeaderContains('Access-Control-Allow-Methods', 'PATCH')
            ->assertHeaderContains('Access-Control-Allow-Headers', 'authorization');
    }

    public function test_meta_requires_a_configured_instance_identity(): void
    {
        config(['casa.mobile.instance_id' => null]);

        $this->getJson('/api/v1/meta')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'PAIRING_NOT_CONFIGURED');
    }

    public function test_meta_requires_an_enabled_https_pairing_endpoint(): void
    {
        config(['app.url' => 'http://'.self::HOST]);

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.pairing.enabled', false);
    }

    public function test_pin_verification_endpoint_is_removed(): void
    {
        $this->postJson('https://'.self::HOST.'/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => '12345678',
        ])
            ->assertNotFound();
    }
}
