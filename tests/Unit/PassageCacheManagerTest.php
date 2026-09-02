<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Morcen\Passage\Http\PassageCacheManager;

beforeEach(function () {
    config(['passage.cache.store' => 'array']);
});

function cacheManagerResponse(array $data = [], int $status = 200): Response
{
    return new Response(new Psr7Response($status, ['Content-Type' => 'application/json'], json_encode($data)));
}

it('returns null on a cache miss', function () {
    $manager = new PassageCacheManager;

    expect($manager->get('GET', 'https://api.example.com/users', []))->toBeNull();
});

it('never caches a non-GET/HEAD method', function () {
    $manager = new PassageCacheManager;

    // A POST request is never cacheable, regardless of what's in the store.
    $manager->put('POST', 'https://api.example.com/users', 60, [], cacheManagerResponse(['id' => 1]));

    expect($manager->get('POST', 'https://api.example.com/users', []))->toBeNull();
});

it('stores and retrieves a GET response round-trip', function () {
    $manager = new PassageCacheManager;

    $manager->put('GET', 'https://api.example.com/users', 60, [], cacheManagerResponse(['id' => 1]), [], []);

    $cached = $manager->get('GET', 'https://api.example.com/users', [], [], []);

    expect($cached)->not->toBeNull()
        ->and($cached->status())->toBe(200)
        ->and($cached->json())->toBe(['id' => 1]);
});

it('does not cache a non-2xx upstream response', function () {
    $manager = new PassageCacheManager;

    $manager->put('GET', 'https://api.example.com/users', 60, [], cacheManagerResponse(['error' => 'boom'], 500));

    expect($manager->get('GET', 'https://api.example.com/users', []))->toBeNull();
});

it('produces a cache key that is stable regardless of query parameter order', function () {
    $manager = new PassageCacheManager;

    $manager->put(
        'GET',
        'https://api.example.com/users',
        60,
        [],
        cacheManagerResponse(['id' => 1]),
        ['a' => '1', 'b' => '2']
    );

    $cached = $manager->get('GET', 'https://api.example.com/users', [], ['b' => '2', 'a' => '1']);

    expect($cached)->not->toBeNull()
        ->and($cached->json())->toBe(['id' => 1]);
});

it('produces a cache key that is stable regardless of header order', function () {
    $manager = new PassageCacheManager;

    $manager->put(
        'GET',
        'https://api.example.com/users',
        60,
        [],
        cacheManagerResponse(['id' => 1]),
        [],
        ['Authorization' => 'Bearer token', 'Accept' => 'application/json']
    );

    $cached = $manager->get(
        'GET',
        'https://api.example.com/users',
        [],
        [],
        ['Accept' => 'application/json', 'Authorization' => 'Bearer token']
    );

    expect($cached)->not->toBeNull()
        ->and($cached->json())->toBe(['id' => 1]);
});

it('does not share a cached response between requests with different headers', function () {
    $manager = new PassageCacheManager;

    $manager->put('GET', 'https://api.example.com/users', 60, [], cacheManagerResponse(['id' => 1]), [], ['Authorization' => 'Bearer one']);

    $cached = $manager->get('GET', 'https://api.example.com/users', [], [], ['Authorization' => 'Bearer two']);

    expect($cached)->toBeNull();
});

it('resolves the cache store from the passage.cache.store config value', function () {
    config(['passage.cache.store' => 'redis']);

    $store = Mockery::mock(Repository::class);
    $store->shouldReceive('get')->once()->andReturn(null);

    Cache::shouldReceive('store')->once()->with('redis')->andReturn($store);

    $manager = new PassageCacheManager;

    expect($manager->get('GET', 'https://api.example.com/users', []))->toBeNull();
});
