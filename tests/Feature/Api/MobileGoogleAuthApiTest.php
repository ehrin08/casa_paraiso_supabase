<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\MobileGoogleOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class MobileGoogleAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'quiet-lotus-123.trycloudflare.com';

    private const INSTANCE_ID = 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811';

    private const DEVICE_ID = 'f688534b-9b27-4ca5-b879-cd52eac79ca9';

    private const STATE = 'c3RhdGUtc3RhdGUtc3RhdGUtc3RhdGUtc3RhdGUtc3Q';

    private const VERIFIER = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://'.self::HOST,
            'casa.mobile.instance_id' => self::INSTANCE_ID,
            'casa.mobile.google_cache_store' => 'array',
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
        ]);
    }

    public function test_mobile_google_redirect_records_pkce_context_and_uses_the_mobile_callback(): void
    {
        $challenge = $this->challenge(self::VERIFIER);
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('redirectUrl')
            ->once()
            ->with('https://'.self::HOST.'/auth/google/mobile/callback')
            ->andReturnSelf();
        $provider->shouldReceive('with')
            ->once()
            ->with(['state' => self::STATE, 'prompt' => 'select_account'])
            ->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect()->away('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get('https://'.self::HOST.'/api/v1/auth/google/redirect?'.http_build_query([
            'instance_id' => self::INSTANCE_ID,
            'device_id' => self::DEVICE_ID,
            'device_name' => 'Casa Paraiso Android',
            'state' => self::STATE,
            'code_challenge' => $challenge,
        ]))->assertRedirect('https://accounts.google.test/oauth')
            ->assertHeaderContains('Cache-Control', 'no-cache');
    }

    public function test_google_callback_exchanges_once_for_a_device_bound_mobile_token(): void
    {
        app(MobileGoogleOAuth::class)->begin([
            'instance_id' => self::INSTANCE_ID,
            'device_id' => self::DEVICE_ID,
            'device_name' => 'Casa Paraiso Android',
            'state' => self::STATE,
            'code_challenge' => $this->challenge(self::VERIFIER),
        ], self::HOST);

        $googleUser = new GoogleUser;
        $googleUser->id = 'google-mobile-1';
        $googleUser->name = 'Mobile Guest';
        $googleUser->email = 'Mobile.Guest@example.test';
        $googleUser->user = ['verified_email' => true];

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $location = $this->get('https://'.self::HOST.'/auth/google/mobile/callback?state='.self::STATE.'&code=google-code')
            ->assertRedirect()
            ->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $callback);

        $this->assertSame(self::STATE, $callback['state']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $callback['code']);

        $payload = [
            'instance_id' => self::INSTANCE_ID,
            'device_id' => self::DEVICE_ID,
            'device_name' => 'Casa Paraiso Android',
            'code' => $callback['code'],
            'code_verifier' => self::VERIFIER,
        ];

        $token = $this->postJson('https://'.self::HOST.'/api/v1/auth/google/exchange', $payload)
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.user.workspace', 'customer')
            ->json('data.token');

        $this->assertDatabaseHas('users', [
            'email' => 'mobile.guest@example.test',
            'google_id' => 'google-mobile-1',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $this->withToken($token)->getJson('https://'.self::HOST.'/api/v1/auth/me')->assertOk();

        $this->postJson('https://'.self::HOST.'/api/v1/auth/google/exchange', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'GOOGLE_EXCHANGE_INVALID');
    }

    public function test_exchange_rejects_a_wrong_pkce_verifier_and_consumes_the_code(): void
    {
        $user = User::factory()->customer()->create();
        $oauth = app(MobileGoogleOAuth::class);
        $authorization = [
            'instance_id' => self::INSTANCE_ID,
            'host' => self::HOST,
            'device_id' => self::DEVICE_ID,
            'device_name' => 'Casa Paraiso Android',
            'code_challenge' => $this->challenge(self::VERIFIER),
        ];
        $code = $oauth->issueExchange($user, $authorization);
        $payload = [
            'instance_id' => self::INSTANCE_ID,
            'device_id' => self::DEVICE_ID,
            'device_name' => 'Casa Paraiso Android',
            'code' => $code,
            'code_verifier' => str_repeat('x', 43),
        ];

        $this->postJson('https://'.self::HOST.'/api/v1/auth/google/exchange', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'GOOGLE_EXCHANGE_INVALID');
        $this->postJson('https://'.self::HOST.'/api/v1/auth/google/exchange', [
            ...$payload,
            'code_verifier' => self::VERIFIER,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
