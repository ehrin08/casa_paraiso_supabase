<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MobileReadCache
{
    private const DASHBOARD_TTL_SECONDS = 120;

    private const OPTIONS_TTL_SECONDS = 900;

    public function __construct(private readonly Repository $cache)
    {
    }

    public function ttlFor(Request $request): ?int
    {
        $name = (string) $request->route()?->getName();

        if (Str::endsWith($name, '.dashboard')) {
            return self::DASHBOARD_TTL_SECONDS;
        }

        return Str::endsWith($name, '.options') ? self::OPTIONS_TTL_SECONDS : null;
    }

    public function keyFor(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        $userId = (int) $request->user()->id;
        $version = $this->version($this->domainFor($name));
        $query = Arr::sortRecursive($request->query());

        return 'mobile-read:'.sha1(json_encode([
            'version' => $version,
            'route' => $name,
            'user' => $userId,
            'query' => $query,
        ], JSON_THROW_ON_ERROR));
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): array
    {
        return $this->cache->remember($key, now()->addSeconds($ttlSeconds), $callback);
    }

    public function invalidate(): void
    {
        foreach (['dashboard', 'options'] as $domain) {
            $key = $this->versionKey($domain);
            $this->cache->add($key, 1, now()->addDays(30));
            $this->cache->increment($key);
        }
    }

    private function version(string $domain): int
    {
        return (int) $this->cache->get($this->versionKey($domain), 1);
    }

    private function domainFor(string $routeName): string
    {
        return Str::endsWith($routeName, '.dashboard') ? 'dashboard' : 'options';
    }

    private function versionKey(string $domain): string
    {
        return "mobile-read-version:{$domain}";
    }
}
