<?php

namespace App\Http\Middleware;

use App\Services\MobileReadCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheMobileReadResponse
{
    public function __construct(private readonly MobileReadCache $cache)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->user() || ! ($ttl = $this->cache->ttlFor($request))) {
            return $next($request);
        }

        $payload = $this->cache->remember($this->cache->keyFor($request), $ttl, function () use ($next, $request): array {
            $response = $next($request);

            return [
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'content_type' => $response->headers->get('Content-Type', 'application/json'),
            ];
        });

        return response($payload['body'], $payload['status'])
            ->header('Content-Type', $payload['content_type'])
            ->header('Cache-Control', 'no-store');
    }
}
