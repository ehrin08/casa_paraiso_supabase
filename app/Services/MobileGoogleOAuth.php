<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class MobileGoogleOAuth
{
    public function begin(array $payload, string $requestHost): void
    {
        $this->assertContext((string) $payload['instance_id'], $requestHost);
        $key = $this->stateKey((string) $payload['state']);

        if (! $this->cache()->add($key, [
            'instance_id' => (string) $payload['instance_id'],
            'host' => $this->publicHost(),
            'device_id' => (string) $payload['device_id'],
            'device_name' => (string) $payload['device_name'],
            'code_challenge' => (string) $payload['code_challenge'],
        ], now()->addSeconds($this->ttl()))) {
            throw new RuntimeException('This Google sign-in request is already active.');
        }
    }

    public function consumeAuthorization(string $state, string $requestHost): array
    {
        if (! $this->validOpaqueValue($state) || ! hash_equals($this->publicHost(), strtolower($requestHost))) {
            throw new RuntimeException('The Google sign-in request is invalid or expired.');
        }

        $key = $this->stateKey($state);

        return $this->cache()->lock('mobile-google-state-lock:'.$key, 10)->block(3, function () use ($key): array {
            $payload = $this->cache()->pull($key);

            if (! is_array($payload)
                || ! hash_equals($this->instanceId(), (string) ($payload['instance_id'] ?? ''))
                || ! hash_equals($this->publicHost(), (string) ($payload['host'] ?? ''))) {
                throw new RuntimeException('The Google sign-in request is invalid or expired.');
            }

            return $payload;
        });
    }

    public function issueExchange(User $user, array $authorization): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->opaqueValue();

            if ($this->cache()->add($this->exchangeKey($code), [
                ...$authorization,
                'user_id' => $user->id,
            ], now()->addSeconds($this->ttl()))) {
                return $code;
            }
        }

        throw new RuntimeException('A secure Google exchange code could not be created.');
    }

    public function consumeExchange(array $payload, string $requestHost): User
    {
        $this->assertContext((string) $payload['instance_id'], $requestHost);
        $code = (string) $payload['code'];
        $key = $this->exchangeKey($code);

        $exchange = $this->cache()->lock('mobile-google-exchange-lock:'.$key, 10)->block(3, function () use ($key): array {
            $value = $this->cache()->pull($key);

            if (! is_array($value)) {
                throw new RuntimeException('The Google exchange code is invalid, expired, or already used.');
            }

            return $value;
        });

        $challenge = $this->challenge((string) $payload['code_verifier']);

        if (! hash_equals((string) ($exchange['instance_id'] ?? ''), (string) $payload['instance_id'])
            || ! hash_equals((string) ($exchange['host'] ?? ''), $this->publicHost())
            || ! hash_equals((string) ($exchange['device_id'] ?? ''), (string) $payload['device_id'])
            || ! hash_equals((string) ($exchange['device_name'] ?? ''), (string) $payload['device_name'])
            || ! hash_equals((string) ($exchange['code_challenge'] ?? ''), $challenge)) {
            throw new RuntimeException('The Google exchange context does not match this phone.');
        }

        $user = User::query()->find($exchange['user_id'] ?? null);

        if (! $user || ! $user->is_active || ! $user->hasVerifiedEmail()) {
            throw new RuntimeException('This account is not eligible for mobile sign-in.');
        }

        return $user;
    }

    public function callbackUrl(array $parameters): string
    {
        return 'casaparaiso://oauth/callback?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function assertContext(string $instanceId, string $requestHost): void
    {
        if (! Str::isUuid($instanceId)
            || ! hash_equals($this->instanceId(), $instanceId)
            || ! hash_equals($this->publicHost(), strtolower($requestHost))) {
            throw new RuntimeException('The Google sign-in request does not belong to this Casa Paraiso server.');
        }
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('casa.mobile.google_cache_store', 'file'));
    }

    private function stateKey(string $state): string
    {
        return 'mobile-google-state:'.$this->digest($state);
    }

    private function exchangeKey(string $code): string
    {
        return 'mobile-google-exchange:'.$this->digest($code);
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', implode('|', [$value, $this->instanceId(), $this->publicHost()]), (string) config('app.key'));
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function opaqueValue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function validOpaqueValue(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $value) === 1;
    }

    private function instanceId(): string
    {
        return (string) config('casa.mobile.instance_id');
    }

    private function publicHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private function ttl(): int
    {
        return (int) config('casa.mobile.google_ttl_seconds', 300);
    }
}
