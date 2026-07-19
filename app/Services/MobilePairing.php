<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class MobilePairing
{
    public function isConfigured(): bool
    {
        return config('casa.mobile.pairing_enabled')
            && Str::isUuid((string) config('casa.mobile.instance_id'))
            && $this->isSecureOrigin((string) config('app.url'));
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Mobile pairing requires an enabled endpoint, a UUID instance ID, and an HTTPS APP_URL.');
        }
    }

    private function isSecureOrigin(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && ! isset($parts['port'], $parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && (($parts['path'] ?? '') === '' || ($parts['path'] ?? '') === '/');
    }
}
