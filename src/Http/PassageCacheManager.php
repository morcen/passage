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
     * (only GET and HEAD responses are cached).
     *
     * @param  int  $ttl  Cache time-to-live in seconds.
     * @param  array  $options  Request options used to generate the cache key.
     * @param  array  $query  Request query parameters used to generate the cache key.
     * @param  array  $headers  Forwarded request headers used to generate the cache key, so
     *                          requests carrying different credentials/identity never share
     *                          a cached response.
     * @return Response|null Cached response, or null on a miss.
     */
    public function get(string $method, string $fullUrl, int $ttl, array $options, array $query = [], array $headers = []): ?Response
    {
        if (! $this->isCacheable($method)) {
            return null;
        }

        $cached = Cache::store($this->store())->get($this->key($method, $fullUrl, $options, $query, $headers));

        if ($cached === null) {
            return null;
        }

        return $this->reconstruct($cached);
    }

    /**
     * Store an upstream response in the cache.
     * No-op for non-cacheable methods or non-2xx responses, so a transient
     * upstream failure is never frozen into the cache and replayed to every
     * client for the full TTL.
     *
     * @param  int  $ttl  Cache time-to-live in seconds.
     * @param  array  $options  Request options used to generate the cache key.
     * @param  Response  $response  The response to store.
     * @param  array  $query  Request query parameters used to generate the cache key.
     * @param  array  $headers  Forwarded request headers used to generate the cache key, so
     *                          requests carrying different credentials/identity never share
     *                          a cached response.
     */
    public function put(string $method, string $fullUrl, int $ttl, array $options, Response $response, array $query = [], array $headers = []): void
    {
        if (! $this->isCacheable($method) || ! $response->successful()) {
            return;
        }

        Cache::store($this->store())->put(
            $this->key($method, $fullUrl, $options, $query, $headers),
            [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ],
            $ttl
        );
    }

    /**
     * Determine if the HTTP method is cacheable.
     */
    private function isCacheable(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD'], true);
    }

    /**
     * Generate a unique cache key for the request.
     *
     * Forwarded headers (e.g. Authorization) are included so two requests that
     * produce different outbound headers never share a cached response.
     */
    private function key(string $method, string $fullUrl, array $options, array $query = [], array $headers = []): string
    {
        ksort($query);
        ksort($headers);

        return 'passage:'.md5($method.$fullUrl.serialize($query).serialize($this->sanitizeForKey($options)).serialize($headers));
    }

    /**
     * Strip non-scalar values (closures, streams, cookie jars, etc.) from a
     * Guzzle options array before it is serialized into the cache key.
     * Guzzle options accept callables (e.g. `on_stats`, `handler`) that
     * serialize() cannot handle and that don't meaningfully identify a
     * cached response anyway.
     */
    private function sanitizeForKey(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeForKey($item), $value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    /**
     * Get the configured cache store name.
     */
    private function store(): ?string
    {
        return config('passage.cache.store');
    }

    /**
     * Reconstruct a Laravel Response object from cached data.
     *
     * @param  array{
     *     status:int,
     *     headers:array<string, array<int, string>|string>,
     *     body:string
     * }  $cached
     */
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
