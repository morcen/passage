<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\Services\PassageService;

beforeEach(function () {
    $this->service = new PassageService;
});

/**
 * Helper: build a mock PendingRequest that expects withHeaders() and returns itself.
 */
function mockPending(): PendingRequest
{
    $mock = Mockery::mock(PendingRequest::class);
    $mock->shouldReceive('withHeaders')->once()->andReturn($mock);

    return $mock;
}

describe('PassageService::callService()', function () {
    describe('GET requests', function () {
        it('forwards query parameters', function () {
            $request = Request::create('/test?page=2&limit=10', 'GET');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('get')
                ->once()
                ->with('items', ['page' => '2', 'limit' => '10'])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'items'))->toBe($mockResponse);
        });

        it('handles GET with no query params', function () {
            $request = Request::create('/test', 'GET');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('get')->once()->with('items', [])->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'items'))->toBe($mockResponse);
        });
    });

    describe('POST requests', function () {
        it('sends form-urlencoded body with asForm()', function () {
            $request = Request::create('/test', 'POST', ['name' => 'Alice', 'role' => 'admin']);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('asForm')->once()->andReturn($pending);
            $pending->shouldReceive('post')
                ->once()
                ->with('users', ['name' => 'Alice', 'role' => 'admin'])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users'))->toBe($mockResponse);
        });

        it('sends JSON body with withBody() when Content-Type is application/json', function () {
            $body = json_encode(['name' => 'Alice']);
            $request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with($body, 'application/json')
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('users')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users'))->toBe($mockResponse);
        });

        it('separates query params from JSON body', function () {
            $body = json_encode(['name' => 'Alice']);
            $request = Request::create('/test?dry_run=1', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withQueryParameters')
                ->once()
                ->with(['dry_run' => '1'])
                ->andReturn($pending);
            $pending->shouldReceive('withBody')
                ->once()
                ->with($body, 'application/json')
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('users')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users'))->toBe($mockResponse);
        });
    });

    describe('PUT requests', function () {
        it('sends form body for PUT', function () {
            $request = Request::create('/test', 'PUT', ['status' => 'active']);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('asForm')->once()->andReturn($pending);
            $pending->shouldReceive('put')
                ->once()
                ->with('users/1', ['status' => 'active'])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users/1'))->toBe($mockResponse);
        });
    });

    describe('DELETE requests', function () {
        it('sends DELETE with no body', function () {
            // Request::create() auto-sets application/x-www-form-urlencoded for DELETE.
            // With no params, asForm() is called with an empty array — harmless but expected.
            $request = Request::create('/test', 'DELETE');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('asForm')->once()->andReturn($pending);
            $pending->shouldReceive('delete')->once()->with('users/1', [])->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users/1'))->toBe($mockResponse);
        });
    });

    describe('header forwarding', function () {
        it('strips hop-by-hop headers', function () {
            $request = Request::create('/test', 'GET');
            $request->headers->set('Connection', 'keep-alive');
            $request->headers->set('Transfer-Encoding', 'chunked');
            $request->headers->set('Upgrade', 'websocket');
            $request->headers->set('X-Custom', 'keep-me');

            $pending = Mockery::mock(PendingRequest::class);
            $pending->shouldReceive('withHeaders')
                ->once()
                ->withArgs(function (array $headers) {
                    return ! array_key_exists('Connection', $headers)
                        && ! array_key_exists('Transfer-Encoding', $headers)
                        && ! array_key_exists('Upgrade', $headers)
                        && array_key_exists('X-Custom', $headers);
                })
                ->andReturn($pending);
            $pending->shouldReceive('get')->andReturn(Mockery::mock(Response::class));

            $this->service->callService($request, $pending, 'test');
        });

        it('forwards custom application headers', function () {
            $request = Request::create('/test', 'GET');
            $request->headers->set('X-Request-Id', 'abc-123');
            $request->headers->set('X-Tenant', 'acme');

            $pending = Mockery::mock(PendingRequest::class);
            $pending->shouldReceive('withHeaders')
                ->once()
                ->withArgs(function (array $headers) {
                    return isset($headers['X-Request-Id']) && isset($headers['X-Tenant']);
                })
                ->andReturn($pending);
            $pending->shouldReceive('get')->andReturn(Mockery::mock(Response::class));

            $this->service->callService($request, $pending, 'test');
        });

        it('forwards Authorization if the handler set it (sensitive headers stripped upstream in PassageController)', function () {
            // By the time callService() is called, PassageController has already stripped
            // the original client Authorization. This simulates a handler that re-added it
            // with a service-level credential.
            $request = Request::create('/test', 'GET');
            $request->headers->set('Authorization', 'Bearer service-token');

            $pending = Mockery::mock(PendingRequest::class);
            $pending->shouldReceive('withHeaders')
                ->once()
                ->withArgs(fn (array $h) => ($h['Authorization'] ?? null) === 'Bearer service-token')
                ->andReturn($pending);
            $pending->shouldReceive('get')->andReturn(Mockery::mock(Response::class));

            $this->service->callService($request, $pending, 'test');
        });

        it('normalizes all-uppercase header names before forwarding', function () {
            $request = Request::create('/test', 'GET');
            $request->headers = new class extends \Symfony\Component\HttpFoundation\HeaderBag
            {
                public function all(?string $key = null): array
                {
                    return ['AUTHORIZATION' => ['Bearer service-token']];
                }
            };

            $pending = Mockery::mock(PendingRequest::class);
            $pending->shouldReceive('withHeaders')
                ->once()
                ->withArgs(fn (array $h) => ($h['Authorization'] ?? null) === 'Bearer service-token')
                ->andReturn($pending);
            $pending->shouldReceive('get')->andReturn(Mockery::mock(Response::class));

            $this->service->callService($request, $pending, 'test');
        });
    });
});
