<?php

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Concerns\HasResilienceOptions;
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

class StreamingHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/', 'passage_streaming' => true];
    }
}

class BaseUriOnlyHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
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

    it('withRetry() is available on PassageHandler subclasses', function () {
        $handler = new RetryableHandler;
        $opts = $handler->getOptions();

        expect($opts)->toHaveKey('passage_retry_times', 3);
        expect($opts)->toHaveKey('passage_retry_sleep_ms', 50);
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
    it('passage_streaming is stripped from guzzle options', function () {
        $request = phase3Route(StreamingHandler::class);

        Http::shouldReceive('withOptions')
            ->withArgs(fn (array $opts) => ! array_key_exists('passage_streaming', $opts))
            ->once()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $upstreamMock = Mockery::mock(Response::class);
        $upstreamMock->shouldReceive('status')->andReturn(200);
        $upstreamMock->shouldReceive('headers')->andReturn([]);
        $upstreamMock->shouldReceive('header')->with('Content-Type')->andReturn('text/event-stream');

        // toPsrResponse() → PSR-7 Response with a stream body
        $stream = Utils::streamFor('data: hello');
        $psr7 = new GuzzleHttp\Psr7\Response(200, [], $stream);
        $upstreamMock->shouldReceive('toPsrResponse')->andReturn($psr7);

        $this->mockService->shouldReceive('callService')->once()->andReturn($upstreamMock);

        $response = phase3Controller()->handle($request);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);
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
