<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Morcen\Passage\Services\PassageService;
use Symfony\Component\HttpFoundation\HeaderBag;

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
        it('forwards the raw form-urlencoded body verbatim', function () {
            $request = Request::create('/test', 'POST', ['name' => 'Alice', 'role' => 'admin']);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with($request->getContent(), 'application/x-www-form-urlencoded')
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('users')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users'))->toBe($mockResponse);
        });

        it('preserves duplicate keys in the forwarded form-urlencoded body', function () {
            $body = 'a=1&a=2';
            $request = Request::create('/test', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ], $body);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with($body, 'application/x-www-form-urlencoded')
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('users')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users'))->toBe($mockResponse);
        });

        it('matches a non-lowercase form-urlencoded Content-Type case-insensitively', function () {
            $body = 'name=Alice&role=admin';
            $request = Request::create('/test', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'APPLICATION/X-WWW-FORM-URLENCODED',
            ], $body);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with($body, 'APPLICATION/X-WWW-FORM-URLENCODED')
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('users')->andReturn($mockResponse);

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

        it('forwards a vendor JSON content type unchanged instead of rewriting it to application/json', function () {
            $body = json_encode(['op' => 'replace', 'path' => '/name', 'value' => 'Alice']);
            $request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/vnd.api+json'], $body);
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with($body, 'application/vnd.api+json')
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
            $pending->shouldReceive('withBody')
                ->once()
                ->with($request->getContent(), 'application/x-www-form-urlencoded')
                ->andReturn($pending);
            $pending->shouldReceive('put')->once()->with('users/1')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users/1'))->toBe($mockResponse);
        });
    });

    describe('DELETE requests', function () {
        it('sends DELETE with no body', function () {
            // Request::create() auto-sets application/x-www-form-urlencoded for DELETE.
            // With no params, withBody() is called with an empty string — harmless but expected.
            $request = Request::create('/test', 'DELETE');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withBody')
                ->once()
                ->with('', 'application/x-www-form-urlencoded')
                ->andReturn($pending);
            $pending->shouldReceive('delete')->once()->with('users/1')->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'users/1'))->toBe($mockResponse);
        });
    });

    describe('OPTIONS requests', function () {
        it('dispatches via send() since PendingRequest has no options() method', function () {
            $request = Request::create('/test', 'OPTIONS');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('send')
                ->once()
                ->with('OPTIONS', 'preflight', [])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'preflight'))->toBe($mockResponse);
        });

        it('forwards query parameters on an OPTIONS request', function () {
            $request = Request::create('/test?debug=1', 'OPTIONS');
            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('withQueryParameters')
                ->once()
                ->with(['debug' => '1'])
                ->andReturn($pending);
            $pending->shouldReceive('send')
                ->once()
                ->with('OPTIONS', 'preflight', [])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'preflight'))->toBe($mockResponse);
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
            $request->headers = new class extends HeaderBag
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

    describe('file uploads', function () {
        it('forwards a single uploaded file as a stream instead of buffering it into memory', function () {
            $file = UploadedFile::fake()->create('avatar.png', 10, 'image/png');
            $request = Request::create('/test', 'POST');
            $request->files->set('avatar', $file);

            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('attach')
                ->once()
                ->withArgs(fn ($name, $contents, $filename, $headers) => $name === 'avatar'
                    && is_resource($contents)
                    && stream_get_contents($contents) === $file->get()
                    && $filename === 'avatar.png'
                    && $headers === ['Content-Type' => 'image/png'])
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('upload', [])->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'upload'))->toBe($mockResponse);
        });

        it('forwards multiple files uploaded under the same field name without crashing', function () {
            $file1 = UploadedFile::fake()->create('one.txt', 5, 'text/plain');
            $file2 = UploadedFile::fake()->create('two.txt', 5, 'text/plain');
            $request = Request::create('/test', 'POST');
            $request->files->set('attachments', [$file1, $file2]);

            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('attach')
                ->once()
                ->withArgs(fn ($name, $contents, $filename, $headers) => $name === 'attachments[0]'
                    && is_resource($contents)
                    && stream_get_contents($contents) === $file1->get()
                    && $filename === 'one.txt'
                    && $headers === ['Content-Type' => 'text/plain'])
                ->andReturn($pending);
            $pending->shouldReceive('attach')
                ->once()
                ->withArgs(fn ($name, $contents, $filename, $headers) => $name === 'attachments[1]'
                    && is_resource($contents)
                    && stream_get_contents($contents) === $file2->get()
                    && $filename === 'two.txt'
                    && $headers === ['Content-Type' => 'text/plain'])
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('upload', [])->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'upload'))->toBe($mockResponse);
        });

        it('forwards a nested/grouped multipart file field without crashing', function () {
            $file = UploadedFile::fake()->create('avatar.png', 10, 'image/png');
            $request = Request::create('/test', 'POST');
            $request->files->set('docs', [0 => ['avatar' => $file]]);

            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('attach')
                ->once()
                ->withArgs(fn ($name, $contents, $filename, $headers) => $name === 'docs[0][avatar]'
                    && is_resource($contents)
                    && stream_get_contents($contents) === $file->get()
                    && $filename === 'avatar.png'
                    && $headers === ['Content-Type' => 'image/png'])
                ->andReturn($pending);
            $pending->shouldReceive('post')->once()->with('upload', [])->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'upload'))->toBe($mockResponse);
        });

        it('forwards the parsed fields of a multipart/form-data request with no file fields', function () {
            // Mirrors PHP's real behavior: multipart requests consume php://input
            // into $_POST before userland code runs, so getContent() is empty.
            $request = Request::create('/test', 'POST', ['field1' => 'hello', 'field2' => 'world'], [], [], [
                'CONTENT_TYPE' => 'multipart/form-data; boundary=----boundary',
            ], '');

            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('post')
                ->once()
                ->with('submit', ['field1' => 'hello', 'field2' => 'world'])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'submit'))->toBe($mockResponse);
        });

        it('detects a multipart/form-data request with no files regardless of Content-Type casing', function () {
            $request = Request::create('/test', 'POST', ['field1' => 'hello'], [], [], [
                'CONTENT_TYPE' => 'MULTIPART/FORM-DATA; boundary=----boundary',
            ], '');

            $mockResponse = Mockery::mock(Response::class);
            $pending = mockPending();
            $pending->shouldReceive('post')
                ->once()
                ->with('submit', ['field1' => 'hello'])
                ->andReturn($mockResponse);

            expect($this->service->callService($request, $pending, 'submit'))->toBe($mockResponse);
        });
    });
});
