<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Concerns\HasResilienceOptions;
use Morcen\Passage\Contracts\AcceptsClientHeaders;
use Morcen\Passage\Events\PassageRequestFailed;
use Morcen\Passage\Events\PassageRequestSending;
use Morcen\Passage\Events\PassageResponseReceived;
use Morcen\Passage\Guards\AllowedHostsGuard;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\Http\PassageCacheManager;
use Morcen\Passage\Http\PassageErrorHandler;
use Morcen\Passage\Http\PassageResponseBuilder;
use Morcen\Passage\PassageHandler;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response as ResponseCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class RetryableHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://api.example.com/'],
            $this->withRetry(3, 50)
        );
    }
}

class CachedHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/', 'passage_cache_ttl' => 60];
    }
}

class CachedHandlerAcceptingAuth extends PassageHandler implements AcceptsClientHeaders
{
    public function allowedClientHeaders(): array
    {
        return ['authorization'];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/', 'passage_cache_ttl' => 60];
    }
}

class StreamingHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/', 'passage_streaming' => true];
    }
}

class CachedStreamingHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return [
            'base_uri' => 'https://api.example.com/',
            'passage_cache_ttl' => 60,
            'passage_streaming' => true,
        ];
    }
}

class BaseUriOnlyHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class CachedHandlerWithCallableOption extends PassageHandler
{
    public function getOptions(): array
    {
        return [
            'base_uri' => 'https://api.example.com/',
            'passage_cache_ttl' => 60,
            'on_stats' => function () {},
        ];
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function phase3Controller(): PassageController
{
    return new PassageController(
        app(PassageServiceInterface::class),
        new PassageResponseBuilder,
        new AllowedHostsGuard,
        new PassageCacheManager,
        new PassageErrorHandler,
        app(),
    );
}

function phase3Route(string $handlerClass, string $uri = '/proxy/items', string $pattern = '/proxy/{path?}'): Request
{
    $request = Request::create($uri, 'GET');
    $route = (new Route(['GET'], $pattern, []))
        ->defaults('_passage_handler', $handlerClass)
        ->where('path', '.*');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function jsonMockResponse(array $data = [], int $status = 200): Response
{
    $mock = Mockery::mock(Response::class);
    $mock->shouldReceive('status')->andReturn($status);
    $mock->shouldReceive('successful')->andReturn($status >= 200 && $status < 300);
    $mock->shouldReceive('json')->andReturn($data);
    $mock->shouldReceive('body')->andReturn(json_encode($data));
    $mock->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
    $mock->shouldReceive('headers')->andReturn([]);

    return $mock;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->mockService = Mockery::mock(PassageServiceInterface::class);
    $this->app->instance(PassageServiceInterface::class, $this->mockService);
    config(['passage.events.enabled' => false]); // silence events by default in most tests
});

describe('3.1 Retry ergonomics', function () {
    it('HasResilienceOptions::withRetry() produces the correct passage_* keys', function () {
        $handler = new class
        {
            use HasResilienceOptions;

            public function options(): array
            {
                return $this->withRetry(3, 200);
            }
        };

        $opts = $handler->options();

        expect($opts)->toMatchArray([
            'passage_retry_times' => 3,
            'passage_retry_sleep_ms' => 200,
        ]);
    });

    it('passage_retry_* keys are stripped from guzzle options', function () {
        $request = phase3Route(RetryableHandler::class);

        Http::shouldReceive('withOptions')
            ->withArgs(function (array $opts) {
                return ! array_key_exists('passage_retry_times', $opts)
                    && ! array_key_exists('passage_retry_sleep_ms', $opts)
                    && $opts['base_uri'] === 'https://api.example.com/';
            })
            ->once()
            ->andReturn(
                Mockery::mock(PendingRequest::class)
                    ->shouldReceive('retry')->once()->andReturnSelf()
                    ->getMock()
            );

        $this->mockService->shouldReceive('callService')
            ->andReturn(jsonMockResponse(['ok' => true]));

        phase3Controller()->handle($request);
    });

    it('calls retry() with throw: false so the real upstream response is always returned', function () {
        $request = phase3Route(RetryableHandler::class);

        Http::shouldReceive('withOptions')->once()->andReturn(
            Mockery::mock(PendingRequest::class)
                ->shouldReceive('retry')
                ->withArgs(fn ($times, $sleepMs, $when, $throw) => $throw === false)
                ->once()
                ->andReturnSelf()
                ->getMock()
        );

        $this->mockService->shouldReceive('callService')
            ->andReturn(jsonMockResponse(['ok' => true]));

        phase3Controller()->handle($request);
    });

    it('withRetry() is available on PassageHandler subclasses', function () {
        $handler = new RetryableHandler;
        $opts = $handler->getOptions();

        expect($opts)->toHaveKey('passage_retry_times', 3);
        expect($opts)->toHaveKey('passage_retry_sleep_ms', 50);
    });

    it('withRetry() defaults passage_retry_when to connection errors and 5xx only, not 4xx', function () {
        $handler = new class
        {
            use HasResilienceOptions;

            public function options(): array
            {
                return $this->withRetry(3, 200);
            }
        };

        $when = $handler->options()['passage_retry_when'];

        expect($when)->toBeCallable();
        expect($when(new ConnectionException('Connection timed out')))->toBeTrue();
        expect($when(new RequestException(new Response(new Psr7Response(500)))))->toBeTrue();
        expect($when(new RequestException(new Response(new Psr7Response(409)))))->toBeFalse();
        expect($when(new RequestException(new Response(new Psr7Response(422)))))->toBeFalse();
    });

    it('withRetry() keeps an explicitly passed $when instead of the default', function () {
        $custom = fn () => false;

        $handler = new class
        {
            use HasResilienceOptions;

            public function options(?callable $when): array
            {
                return $this->withRetry(3, 200, $when);
            }
        };

        expect($handler->options($custom))->toHaveKey('passage_retry_when', $custom);
    });
});

describe('3.2 Response caching', function () {
    it('caches a GET response and serves it on the second call without hitting upstream', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $request = phase3Route(CachedHandler::class);

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // First call: upstream is hit once
        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse(['cached' => true]));

        $controller = phase3Controller();
        $first = $controller->handle($request);

        // Second call: cache hit, upstream not called again
        $second = phase3Controller()->handle(phase3Route(CachedHandler::class));

        expect($first->getStatusCode())->toBe(200);
        expect($second->getStatusCode())->toBe(200);
    });

    it('does not cache a non-2xx upstream response', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $request = phase3Route(CachedHandler::class);

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // Both calls hit upstream: the first response is a transient 503, so it
        // must never be cached and replayed to the second request.
        $this->mockService->shouldReceive('callService')
            ->twice()
            ->andReturn(jsonMockResponse(['error' => 'unavailable'], 503));

        $first = phase3Controller()->handle($request);
        $second = phase3Controller()->handle(phase3Route(CachedHandler::class));

        expect($first->getStatusCode())->toBe(503);
        expect($second->getStatusCode())->toBe(503);
    });

    it('does not serve a cached response to a request with a different query string', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $requestPageOne = phase3Route(CachedHandler::class, '/proxy/items?page=1');
        $requestPageTwo = phase3Route(CachedHandler::class, '/proxy/items?page=2');

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // Each distinct query string is a cache miss, so upstream is hit for both.
        $this->mockService->shouldReceive('callService')
            ->twice()
            ->andReturn(jsonMockResponse(['ok' => true]));

        $first = phase3Controller()->handle($requestPageOne);
        $second = phase3Controller()->handle($requestPageTwo);

        expect($first->getStatusCode())->toBe(200);
        expect($second->getStatusCode())->toBe(200);
    });

    it('does not serve a cached response to a request with a different Authorization header', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $requestUserOne = phase3Route(CachedHandlerAcceptingAuth::class);
        $requestUserOne->headers->set('Authorization', 'Bearer user-one-token');

        $requestUserTwo = phase3Route(CachedHandlerAcceptingAuth::class);
        $requestUserTwo->headers->set('Authorization', 'Bearer user-two-token');

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // Each distinct Authorization header is a cache miss, so upstream is hit for
        // both requests — user two must never be served user one's cached response.
        $this->mockService->shouldReceive('callService')
            ->twice()
            ->andReturn(jsonMockResponse(['ok' => true]));

        $first = phase3Controller()->handle($requestUserOne);
        $second = phase3Controller()->handle($requestUserTwo);

        expect($first->getStatusCode())->toBe(200);
        expect($second->getStatusCode())->toBe(200);
    });

    it('serves a cached response to a request with the same Authorization header', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $first = phase3Route(CachedHandlerAcceptingAuth::class);
        $first->headers->set('Authorization', 'Bearer same-token');

        $second = phase3Route(CachedHandlerAcceptingAuth::class);
        $second->headers->set('Authorization', 'Bearer same-token');

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // Same Authorization header on both requests: only the first hits upstream.
        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse(['ok' => true]));

        $firstResponse = phase3Controller()->handle($first);
        $secondResponse = phase3Controller()->handle($second);

        expect($firstResponse->getStatusCode())->toBe(200);
        expect($secondResponse->getStatusCode())->toBe(200);
    });

    it('does not cache non-GET methods', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $request = Request::create('/proxy/items', 'POST');
        $route = (new Route(['POST'], '/proxy/{path?}', []))
            ->defaults('_passage_handler', CachedHandler::class)
            ->where('path', '.*');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // Both calls hit upstream
        $this->mockService->shouldReceive('callService')
            ->twice()
            ->andReturn(jsonMockResponse(['ok' => true]));

        phase3Controller()->handle($request);

        $request2 = Request::create('/proxy/items', 'POST');
        $route2 = (new Route(['POST'], '/proxy/{path?}', []))
            ->defaults('_passage_handler', CachedHandler::class)
            ->where('path', '.*');
        $route2->bind($request2);
        $request2->setRouteResolver(fn () => $route2);

        phase3Controller()->handle($request2);
    });

    it('passage_cache_ttl is stripped from guzzle options', function () {
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        $request = phase3Route(CachedHandler::class);

        Http::shouldReceive('withOptions')
            ->withArgs(fn (array $opts) => ! array_key_exists('passage_cache_ttl', $opts))
            ->once()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse([]));

        phase3Controller()->handle($request);
    });

    it('serves a cached GET response when enforce_allowed_hosts is also enabled', function () {
        // Regression test for #122: enforce_allowed_hosts injects an on_redirect
        // Closure into allow_redirects, which used to reach
        // PassageCacheManager::key()'s serialize() call and crash every request.
        Cache::flush();
        config([
            'passage.cache.store' => 'array',
            'passage.security.enforce_allowed_hosts' => true,
            'passage.security.allowed_hosts' => ['api.example.com'],
        ]);

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse(['ok' => true]));

        $first = phase3Controller()->handle(phase3Route(CachedHandler::class));
        $second = phase3Controller()->handle(phase3Route(CachedHandler::class));

        expect($first->getStatusCode())->toBe(200);
        expect($second->getStatusCode())->toBe(200);
    });

    it('serves a cached GET response when a handler sets a callable guzzle option', function () {
        // Regression test for #84: PassageCacheManager::key() used to
        // serialize() the full options array unconditionally, which throws
        // when a handler sets a callable Guzzle option (e.g. on_stats)
        // directly, crashing every request on the cached route.
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        Http::shouldReceive('withOptions')->twice()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse(['ok' => true]));

        $first = phase3Controller()->handle(phase3Route(CachedHandlerWithCallableOption::class));
        $second = phase3Controller()->handle(phase3Route(CachedHandlerWithCallableOption::class));

        expect($first->getStatusCode())->toBe(200);
        expect($second->getStatusCode())->toBe(200);
    });
});

describe('3.3 Upstream error handling', function () {
    it('returns 502 when upstream is unreachable', function () {
        $request = phase3Route(BaseUriOnlyHandler::class);

        Http::shouldReceive('withOptions')->once()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        $this->mockService->shouldReceive('callService')
            ->once()
            ->andThrow(new ConnectionException('Connection refused'));

        $response = phase3Controller()->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_BAD_GATEWAY);
        expect($response->getData(true))->toHaveKey('error');
    });

    it('returns 500 for unexpected exceptions', function () {
        $request = phase3Route(BaseUriOnlyHandler::class);

        Http::shouldReceive('withOptions')->once()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        $this->mockService->shouldReceive('callService')
            ->once()
            ->andThrow(new RuntimeException('Something went wrong'));

        $response = phase3Controller()->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_INTERNAL_SERVER_ERROR);
    });

    it('passes through upstream 4xx and 5xx without treating them as errors', function () {
        $request = phase3Route(BaseUriOnlyHandler::class);

        Http::shouldReceive('withOptions')->once()->andReturn(
            Mockery::mock(PendingRequest::class)
        );

        // 422 from upstream is a valid response — not a transport failure
        $this->mockService->shouldReceive('callService')
            ->once()
            ->andReturn(jsonMockResponse(['message' => 'Unprocessable'], 422));

        $response = phase3Controller()->handle($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

describe('3.4 Streaming', function () {
    it('passage_streaming is stripped from guzzle options and replaced with a Guzzle stream option', function () {
        $request = phase3Route(StreamingHandler::class);

        Http::shouldReceive('withOptions')
            ->withArgs(fn (array $opts) => ! array_key_exists('passage_streaming', $opts) && ($opts['stream'] ?? null) === true)
            ->once()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $upstreamMock = Mockery::mock(Response::class);
        $upstreamMock->shouldReceive('status')->andReturn(200);
        $upstreamMock->shouldReceive('headers')->andReturn([]);
        $upstreamMock->shouldReceive('header')->with('Content-Type')->andReturn('text/event-stream');

        // toPsrResponse() → PSR-7 Response with a stream body
        $stream = Utils::streamFor('data: hello');
        $psr7 = new Psr7Response(200, [], $stream);
        $upstreamMock->shouldReceive('toPsrResponse')->andReturn($psr7);

        $this->mockService->shouldReceive('callService')->once()->andReturn($upstreamMock);

        $response = phase3Controller()->handle($request);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);
    });

    it('does not set the Guzzle stream option when passage_streaming is not enabled', function () {
        $request = phase3Route(BaseUriOnlyHandler::class);

        Http::shouldReceive('withOptions')
            ->withArgs(fn (array $opts) => ! array_key_exists('stream', $opts))
            ->once()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $this->mockService->shouldReceive('callService')->once()->andReturn(jsonMockResponse());

        $response = phase3Controller()->handle($request);

        expect($response->getStatusCode())->toBe(200);
    });

    it('does not truncate the streamed body when passage_cache_ttl is also set', function () {
        // Regression test for #85: caching used to read the upstream body via
        // Response::body() before buildStreamedResponse() got a chance to read
        // from the same lazy PSR-7 stream, serving an empty/truncated body to
        // the client. Caching is now disabled whenever streaming is enabled.
        Cache::flush();
        config(['passage.cache.store' => 'array']);

        Http::shouldReceive('withOptions')
            ->withArgs(fn (array $opts) => ($opts['stream'] ?? null) === true)
            ->twice()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $streamMockResponse = function () {
            $upstream = Mockery::mock(Response::class);
            $upstream->shouldReceive('status')->andReturn(200);
            $upstream->shouldReceive('headers')->andReturn([]);
            $upstream->shouldReceive('header')->with('Content-Type')->andReturn('text/event-stream');

            $stream = Utils::streamFor('data: hello');
            $psr7 = new Psr7Response(200, [], $stream);
            $upstream->shouldReceive('toPsrResponse')->andReturn($psr7);

            return $upstream;
        };

        // Both requests must reach the upstream: since caching is disabled while
        // streaming, there is never a cache hit for this handler.
        $this->mockService->shouldReceive('callService')
            ->twice()
            ->andReturn($streamMockResponse());

        $first = phase3Controller()->handle(phase3Route(CachedStreamingHandler::class));

        ob_start();
        $first->sendContent();
        $firstBody = ob_get_clean();

        $second = phase3Controller()->handle(phase3Route(CachedStreamingHandler::class));

        expect($first)->toBeInstanceOf(StreamedResponse::class);
        expect($firstBody)->toBe('data: hello');
        expect($second)->toBeInstanceOf(StreamedResponse::class);
    });
});

describe('3.5 Events', function () {
    beforeEach(function () {
        config(['passage.events.enabled' => true]);
    });

    it('fires PassageRequestSending before the upstream call', function () {
        Event::fake([PassageRequestSending::class, PassageResponseReceived::class]);

        $request = phase3Route(BaseUriOnlyHandler::class);
        Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));
        $this->mockService->shouldReceive('callService')->once()->andReturn(jsonMockResponse());

        phase3Controller()->handle($request);

        Event::assertDispatched(PassageRequestSending::class, function ($e) {
            return $e->handler === BaseUriOnlyHandler::class;
        });
    });

    it('fires PassageResponseReceived after successful upstream call', function () {
        Event::fake([PassageRequestSending::class, PassageResponseReceived::class]);

        $request = phase3Route(BaseUriOnlyHandler::class);
        Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));
        $this->mockService->shouldReceive('callService')->once()->andReturn(jsonMockResponse(['ok' => true]));

        phase3Controller()->handle($request);

        Event::assertDispatched(PassageResponseReceived::class, function ($e) {
            return $e->handler === BaseUriOnlyHandler::class
                && $e->cached === false
                && $e->durationMs >= 0;
        });
    });

    it('fires PassageRequestFailed on transport errors', function () {
        Event::fake([PassageRequestSending::class, PassageRequestFailed::class]);

        $request = phase3Route(BaseUriOnlyHandler::class);
        Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));
        $this->mockService->shouldReceive('callService')
            ->once()
            ->andThrow(new ConnectionException('Timeout'));

        phase3Controller()->handle($request);

        Event::assertDispatched(PassageRequestFailed::class, function ($e) {
            return $e->handler === BaseUriOnlyHandler::class;
        });
    });

    it('does not fire events when passage.events.enabled is false', function () {
        config(['passage.events.enabled' => false]);
        Event::fake();

        $request = phase3Route(BaseUriOnlyHandler::class);
        Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));
        $this->mockService->shouldReceive('callService')->once()->andReturn(jsonMockResponse());

        phase3Controller()->handle($request);

        Event::assertNothingDispatched();
    });
});
