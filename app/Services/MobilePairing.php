<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class MobilePairing
{
    public function issue(): array
    {
        $this->assertConfigured();

        $cache = $this->cache();
        $instanceId = $this->instanceId();
        $host = $this->publicHost();
        $ttl = (int) config('casa.mobile.pairing_ttl_seconds', 300);

        return $cache->lock($this->activeLockKey(), 10)->block(3, function () use ($cache, $instanceId, $host, $ttl): array {
            if ($activeKey = $cache->get($this->activeKey())) {
                $cache->forget((string) $activeKey);
            }

            for ($attempt = 0; $attempt < 10; $attempt++) {
                $code = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
                $key = $this->codeKey($code, $instanceId, $host);

                if (! $cache->add($key, [
                    'instance_id' => $instanceId,
                    'host' => $host,
                ], now()->addSeconds($ttl))) {
                    continue;
                }

                $cache->put($this->activeKey(), $key, now()->addSeconds($ttl));

                return [
                    'code' => $code,
                    'instance_id' => $instanceId,
                    'base_url' => rtrim((string) config('app.url'), '/'),
                    'expires_at' => now(config('app.timezone'))->addSeconds($ttl)->toIso8601String(),
                ];
            }

            throw new RuntimeException('A unique mobile pairing code could not be created.');
        });
    }

    public function consume(string $code, string $instanceId, string $requestHost): bool
    {
        if (! $this->isConfigured() || ! Str::isUuid($instanceId) || ! preg_match('/^\d{8}$/', $code)) {
            return false;
        }

        $expectedHost = $this->publicHost();

        if (! hash_equals($this->instanceId(), $instanceId) || ! hash_equals($expectedHost, strtolower($requestHost))) {
            return false;
        }

        $cache = $this->cache();
        $key = $this->codeKey($code, $instanceId, $expectedHost);

        return (bool) $cache->lock('mobile-pairing-lock:'.$key, 10)->block(3, function () use ($cache, $key, $instanceId, $expectedHost): bool {
            $payload = $cache->pull($key);

            if (! is_array($payload)
                || ! hash_equals((string) ($payload['instance_id'] ?? ''), $instanceId)
                || ! hash_equals((string) ($payload['host'] ?? ''), $expectedHost)) {
                return false;
            }

            if ($cache->get($this->activeKey()) === $key) {
                $cache->forget($this->activeKey());
            }

            return true;
        });
    }

    public function isConfigured(): bool
    {
        return config('casa.mobile.demo_enabled')
            && Str::isUuid((string) config('casa.mobile.instance_id'))
            && $this->isQuickTunnelUrl((string) config('app.url'));
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Mobile pairing requires an enabled demo, a UUID instance ID, and an HTTPS trycloudflare.com APP_URL.');
        }
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('casa.mobile.pairing_cache_store', 'file'));
    }

    private function instanceId(): string
    {
        return (string) config('casa.mobile.instance_id');
    }

    private function publicHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private function isQuickTunnelUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && ! isset($parts['port'], $parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && (($parts['path'] ?? '') === '' || ($parts['path'] ?? '') === '/')
            && preg_match('/^[a-z0-9-]+\.trycloudflare\.com$/', strtolower($parts['host'])) === 1;
    }

    private function activeKey(): string
    {
        return 'mobile-pairing-active:'.$this->instanceId();
    }

    private function activeLockKey(): string
    {
        return 'mobile-pairing-active-lock:'.$this->instanceId();
    }

    private function codeKey(string $code, string $instanceId, string $host): string
    {
        return 'mobile-pairing-code:'.hash_hmac('sha256', implode('|', [$code, $instanceId, $host]), (string) config('app.key'));
    }
}
