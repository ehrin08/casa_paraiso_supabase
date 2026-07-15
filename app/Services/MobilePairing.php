<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class MobilePairing
{
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
}
