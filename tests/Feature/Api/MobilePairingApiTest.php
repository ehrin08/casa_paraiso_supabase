<?php

namespace Tests\Feature\Api;

use App\Services\MobilePairing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MobilePairingApiTest extends TestCase
{
    private const INSTANCE_ID = 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811';

    private const HOST = 'quiet-lotus-1234.trycloudflare.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:pairing-test-application-key',
            'app.url' => 'https://'.self::HOST,
            'casa.mobile.demo_enabled' => true,
            'casa.mobile.instance_id' => self::INSTANCE_ID,
            'casa.mobile.pairing_cache_store' => 'array',
        ]);

        Cache::store('array')->flush();
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
            ->assertJsonPath('data.pairing.protocol', 1)
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

    public function test_meta_requires_a_configured_instance_identity(): void
    {
        config(['casa.mobile.instance_id' => null]);

        $this->getJson('/api/v1/meta')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'PAIRING_NOT_CONFIGURED');
    }

    public function test_pairing_code_is_single_use_and_not_reusable(): void
    {
        $code = app(MobilePairing::class)->issue()['code'];

        $this->postJson('https://'.self::HOST.'/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => $code,
        ])
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.instance_id', self::INSTANCE_ID)
            ->assertJsonPath('data.pairing_protocol', 1);

        $this->postJson('https://'.self::HOST.'/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAIRING_CODE_INVALID_OR_EXPIRED');
    }

    public function test_pairing_rejects_another_server_host_without_consuming_the_code(): void
    {
        $code = app(MobilePairing::class)->issue()['code'];

        $this->postJson('https://other-instance.trycloudflare.com/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => $code,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAIRING_CONTEXT_MISMATCH');

        $this->postJson('https://'.self::HOST.'/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => $code,
        ])
            ->assertOk();
    }

    public function test_pairing_rejects_invalid_codes_without_disclosing_which_part_failed(): void
    {
        $this->postJson('https://'.self::HOST.'/api/v1/pairings/verify', [
            'instance_id' => self::INSTANCE_ID,
            'code' => 'not-a-code',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAIRING_CODE_INVALID_OR_EXPIRED');
    }

    public function test_artisan_command_emits_a_machine_readable_pairing_code(): void
    {
        Artisan::call('casa:mobile-pairing-code', ['--json' => true]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertMatchesRegularExpression('/^\d{8}$/', $result['code']);
        $this->assertSame(self::INSTANCE_ID, $result['instance_id']);
        $this->assertSame('https://'.self::HOST, $result['base_url']);
    }
}
