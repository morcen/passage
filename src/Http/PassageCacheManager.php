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
     *
     * @param  string  $method   HTTP method (e.g., GET, POST).
     * @param  string  $fullUrl  The full URL being requested.
     * @param  int     $ttl      Cache time-to-live in seconds.
     * @param  array   $options  Request options used to generate the cache key.
     * @return \Illuminate\Http\Client\Response|null  The cached response or null on miss.
     *
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
     *
     * @param  string  $method    HTTP method (e.g., GET, HEAD).
     * @param  string  $fullUrl   The full URL being requested.
     * @param  int     $ttl       Cache time-to-live in seconds.
     * @param  array   $options   Request options used to generate the cache key.
     * @param  \Illuminate\Http\Client\Response  $response  The response to store.
     * @return void
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

    /**
     * Determine if the HTTP method is cacheable.
     *
     * @param  string  $method
     * @return bool
     */

    private function isCacheable(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD'], strict: true);
    }

    /**
     * Generate a unique cache key for the request.
     *
     * @param  string  $method
     * @param  string  $fullUrl
     * @param  array   $options
     * @return string
     */

    private function key(string $method, string $fullUrl, array $options): string
    {
        return 'passage:'.md5($method.$fullUrl.serialize($options));
    }

    /**
     * Get the configured cache store name.
     *
     * @return string|null
     */

    private function store(): ?string
    {
        return config('passage.cache.store');
    }

    /**
     * Reconstruct a Laravel Response object from cached data.
     *
     * @param  array  $cached
     * @return \Illuminate\Http\Client\Response
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
