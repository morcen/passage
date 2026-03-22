<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

// Fixture: a passage controller that transforms both request and response
class TestPassageController implements PassageControllerInterface
{
    public function getRequest(Request $request): Request
    {
        $request->headers->set('X-Custom-Header', 'test-value');
        $request->merge(['extra_field' => 'added']);

        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        $data = $response->json();
        $data['controller_processed'] = true;

        $mock = Mockery::mock(Response::class);
        $mock->shouldReceive('json')->andReturn($data);
        $mock->shouldReceive('status')->andReturn($response->status());

        return $mock;
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.custom.com/'];
    }
}

// Fixture: a passage controller that only transforms requests
class TestRequestOnlyPassageController implements PassageControllerInterface
{
    public function getRequest(Request $request): Request
    {
        $request->headers->set('Authorization', 'Bearer injected-token');

        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        return $response;
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.custom.com/'];
    }
}

// Fixture: a passage controller that only transforms responses
class TestResponseOnlyPassageController implements PassageControllerInterface
{
    public function getRequest(Request $request): Request
    {
        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        $data = $response->json();
        $data['response_enriched'] = true;

        $mock = Mockery::mock(Response::class);
        $mock->shouldReceive('json')->andReturn($data);
        $mock->shouldReceive('status')->andReturn(201);

        return $mock;
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.custom.com/'];
    }
}

beforeEach(function () {
    $this->mockPassageService = Mockery::mock(PassageServiceInterface::class);
    $this->controller = new PassageController($this->mockPassageService);
});

describe('PassageController', function () {
    it('returns 404 for non-existent service', function () {
        Http::shouldReceive('hasMacro')
            ->with('nonexistent')
            ->once()
            ->andReturn(false);

        $request = Request::create('/nonexistent/test', 'GET');

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_NOT_FOUND);
        expect($response->getData(true))->toBe(['error' => 'Route not found']);
    });

    it('handles basic service request successfully', function () {
        $request = Request::create('/testservice/users/123', 'GET');

        Http::shouldReceive('hasMacro')
            ->with('testservice')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('testservice')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['id' => 123, 'name' => 'John']);
        $mockResponse->shouldReceive('status')
            ->once()
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->with($request, $mockPendingRequest, 'users/123')
            ->once()
            ->andReturn($mockResponse);

        config(['passage.services.testservice' => ['base_uri' => 'https://api.example.com/']]);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['id' => 123, 'name' => 'John']);
    });

    it('extracts correct URI parts', function () {
        $request = Request::create('/github/users/morcen/repos', 'GET');

        Http::shouldReceive('hasMacro')
            ->with('github')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('github')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['repos' => []]);
        $mockResponse->shouldReceive('status')
            ->once()
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->with($request, $mockPendingRequest, 'users/morcen/repos')
            ->once()
            ->andReturn($mockResponse);

        config(['passage.services.github' => ['base_uri' => 'https://api.github.com/']]);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
    });

    // Custom controller test removed due to complexity of mocking class instantiation

    it('handles empty URI paths', function () {
        $request = Request::create('/service', 'GET');

        Http::shouldReceive('hasMacro')
            ->with('service')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('service')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['data' => 'root']);
        $mockResponse->shouldReceive('status')
            ->once()
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->with($request, $mockPendingRequest, '')
            ->once()
            ->andReturn($mockResponse);

        config(['passage.services.service' => ['base_uri' => 'https://api.service.com/']]);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
    });

    it('calls getRequest on a controller-based passage handler', function () {
        $request = Request::create('/customservice/users/1', 'GET');

        config(['passage.services.customservice' => TestPassageController::class]);

        Http::shouldReceive('hasMacro')
            ->with('customservice')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('customservice')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->andReturn(['id' => 1]);
        $mockResponse->shouldReceive('status')
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req, $pending, string $uri) {
                return $req->header('X-Custom-Header') === 'test-value'
                    && $req->input('extra_field') === 'added'
                    && $uri === 'users/1';
            })
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
    });

    it('calls getResponse on a controller-based passage handler', function () {
        $request = Request::create('/customservice/items', 'GET');

        config(['passage.services.customservice' => TestPassageController::class]);

        Http::shouldReceive('hasMacro')
            ->with('customservice')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('customservice')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['items' => []]);
        $mockResponse->shouldReceive('status')
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toMatchArray(['controller_processed' => true]);
    });

    it('applies only request transformation when response passthrough is used', function () {
        $request = Request::create('/authservice/profile', 'GET');

        config(['passage.services.authservice' => TestRequestOnlyPassageController::class]);

        Http::shouldReceive('hasMacro')
            ->with('authservice')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('authservice')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->andReturn(['user' => 'morcen']);
        $mockResponse->shouldReceive('status')
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req) {
                return $req->header('Authorization') === 'Bearer injected-token';
            })
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['user' => 'morcen']);
    });

    it('applies only response transformation when request passthrough is used', function () {
        $request = Request::create('/enriched/posts', 'GET');

        config(['passage.services.enriched' => TestResponseOnlyPassageController::class]);

        Http::shouldReceive('hasMacro')
            ->with('enriched')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('enriched')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['posts' => [1, 2, 3]]);
        $mockResponse->shouldReceive('status')
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(201);
        expect($response->getData(true))->toMatchArray([
            'posts' => [1, 2, 3],
            'response_enriched' => true,
        ]);
    });

    it('skips controller hooks when handler is an array config', function () {
        $request = Request::create('/plain/data', 'GET');

        config(['passage.services.plain' => ['base_uri' => 'https://plain.api.com/']]);

        Http::shouldReceive('hasMacro')
            ->with('plain')
            ->once()
            ->andReturn(true);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('plain')
            ->once()
            ->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->andReturn(['raw' => true]);
        $mockResponse->shouldReceive('status')
            ->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->index($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['raw' => true]);
    });

    it('handles different HTTP methods', function () {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/api/endpoint', $method, ['test' => 'data']);

            Http::shouldReceive('hasMacro')
                ->with('api')
                ->once()
                ->andReturn(true);

            $mockPendingRequest = Mockery::mock(PendingRequest::class);
            Http::shouldReceive('api')
                ->once()
                ->andReturn($mockPendingRequest);

            $mockResponse = Mockery::mock(Response::class);
            $mockResponse->shouldReceive('json')
                ->once()
                ->andReturn(['method' => $method]);
            $mockResponse->shouldReceive('status')
                ->once()
                ->andReturn(200);

            $this->mockPassageService->shouldReceive('callService')
                ->with($request, $mockPendingRequest, 'endpoint')
                ->once()
                ->andReturn($mockResponse);

            config(['passage.services.api' => ['base_uri' => 'https://api.test.com/']]);

            $response = $this->controller->index($request);

            expect($response->getStatusCode())->toBe(200);
        }
    });
});
