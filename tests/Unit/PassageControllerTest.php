<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

// Fixture: transforms both request and response
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

// Fixture: request-only transformation
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

// Fixture: response-only transformation
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
    $this->app->instance(PassageServiceInterface::class, $this->mockPassageService);
    $this->controller = new PassageController($this->mockPassageService);
});

describe('PassageController', function () {
    it('returns 404 when no handler is set in route defaults', function () {
        $request = Request::create('/no-handler', 'GET');
        $route = new \Illuminate\Routing\Route(['GET'], '/no-handler', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_NOT_FOUND);
        expect($response->getData(true))->toBe(['error' => 'Route not found']);
    });

    it('returns 404 when handler does not implement PassageControllerInterface', function () {
        $request = Request::create('/bad-handler', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/bad-handler', []))
            ->defaults('_passage_handler', \stdClass::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_NOT_FOUND);
    });

    it('proxies a basic GET request and returns JSON response', function () {
        $request = Request::create('/github/users/123', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn(['id' => 123]);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req, $pending, string $uri) use ($mockPendingRequest) {
                return $req->header('X-Custom-Header') === 'test-value'
                    && $pending === $mockPendingRequest;
            })
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toMatchArray(['id' => 123, 'controller_processed' => true]);
    });

    it('extracts the path route parameter as the forwarded URI', function () {
        $request = Request::create('/github/users/morcen/repos', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestPassageController::class)
            ->where('path', '.*');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn([]);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req, $pending, string $uri) {
                return $uri === 'users/morcen/repos';
            })
            ->once()
            ->andReturn($mockResponse);

        $this->controller->handle($request);
    });

    it('applies only request transformation when response is passed through', function () {
        $request = Request::create('/auth/profile', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/auth/{path?}', []))
            ->defaults('_passage_handler', TestRequestOnlyPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn(['user' => 'morcen']);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req) {
                return $req->header('Authorization') === 'Bearer injected-token';
            })
            ->once()
            ->andReturn($mockResponse);

        $response = $this->controller->handle($request);

        expect($response->getData(true))->toBe(['user' => 'morcen']);
    });

    it('applies only response transformation when request is passed through', function () {
        $request = Request::create('/enriched/posts', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/enriched/{path?}', []))
            ->defaults('_passage_handler', TestResponseOnlyPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn(['posts' => [1, 2, 3]]);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')->once()->andReturn($mockResponse);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(201);
        expect($response->getData(true))->toMatchArray(['posts' => [1, 2, 3], 'response_enriched' => true]);
    });

    it('merges global passage options with handler options', function () {
        config(['passage.options' => ['timeout' => 60, 'http_errors' => false]]);

        $request = Request::create('/github/users', 'GET');
        $route = (new \Illuminate\Routing\Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        Http::shouldReceive('withOptions')
            ->withArgs(function (array $opts) {
                return $opts['timeout'] === 60
                    && $opts['base_uri'] === 'https://api.custom.com/';
            })
            ->once()
            ->andReturn(Mockery::mock(PendingRequest::class));

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn([]);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $this->mockPassageService->shouldReceive('callService')->once()->andReturn($mockResponse);

        $this->controller->handle($request);
    });
});
