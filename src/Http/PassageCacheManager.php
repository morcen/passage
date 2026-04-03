<?php

namespace Morcen\Passage\Http;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

class PassageCacheManager
{
    /**
     * Attempt to return a cached response.
     * Returns null on a cache miss, or if caching is not applicable.
     */
    public function get(string $method, string $fullUrl, int $ttl, array $options): ?Response
    {
        if (! $this->isCacheable($method)) {
            return null;
        }

        $cached = Cache::store($this->store())->get($this->key($method, $fullUrl, $options));

        if ($cached === null) {
            return null;
        }

        return $this->reconstruct($cached);
    }

    /**
     * Store an upstream response in the cache.
     * No-op for non-cacheable methods.
     */
    public function put(string $method, string $fullUrl, int $ttl, array $options, Response $response): void
    {
        if (! $this->isCacheable($method)) {
            return;
        }

        Cache::store($this->store())->put(
            $this->key($method, $fullUrl, $options),
            [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ],
            $ttl
        );
    }

    private function isCacheable(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD'], strict: true);
    }

    private function key(string $method, string $fullUrl, array $options): string
    {
        return 'passage:'.md5($method.$fullUrl.serialize($options));
    }

    private function store(): ?string
    {
        return config('passage.cache.store');
    }

    private function reconstruct(array $cached): Response
    {
        $psr7 = new Psr7Response(
            $cached['status'],
            $cached['headers'],
            $cached['body']
        );

        return new Response($psr7);
    }
}
