<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Exceptions\PassageRequestAbortedException;
use Morcen\Passage\Guards\AllowedHostsGuard;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\Http\PassageCacheManager;
use Morcen\Passage\Http\PassageErrorHandler;
use Morcen\Passage\Http\PassageResponseBuilder;
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
        $mock->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
        $mock->shouldReceive('headers')->andReturn([]);
        $mock->shouldReceive('body')->andReturn(json_encode($data));

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
        $mock->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
        $mock->shouldReceive('headers')->andReturn([]);
        $mock->shouldReceive('body')->andReturn(json_encode($data));

        return $mock;
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.custom.com/'];
    }
}

// Fixture: missing base_uri
class TestNoBaseUriPassageController implements PassageControllerInterface
{
    public function getRequest(Request $request): Request
    {
        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        return $response;
    }

    public function getOptions(): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->mockPassageService = Mockery::mock(PassageServiceInterface::class);
    $this->app->instance(PassageServiceInterface::class, $this->mockPassageService);
    $this->controller = new PassageController(
        $this->mockPassageService,
        new PassageResponseBuilder,
        new AllowedHostsGuard,
        new PassageCacheManager,
        new PassageErrorHandler,
        app(),
    );
});

/**
 * Helper: build a mock upstream Response that PassageResponseBuilder can consume.
 */
function jsonUpstreamResponse(array $data, int $status = 200): Response
{
    $mock = Mockery::mock(Response::class);
    $mock->shouldReceive('status')->andReturn($status);
    $mock->shouldReceive('json')->andReturn($data);
    $mock->shouldReceive('body')->andReturn(json_encode($data));
    $mock->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
    $mock->shouldReceive('headers')->andReturn([]);

    return $mock;
}

describe('PassageController', function () {
    it('returns 404 when no handler is set in route defaults', function () {
        $request = Request::create('/no-handler', 'GET');
        $route = new Route(['GET'], '/no-handler', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_NOT_FOUND);
        expect($response->getData(true))->toBe(['error' => 'Route not found']);
    });

    it('returns 404 when handler does not implement PassageControllerInterface', function () {
        $request = Request::create('/bad-handler', 'GET');
        $route = (new Route(['GET'], '/bad-handler', []))
            ->defaults('_passage_handler', stdClass::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_NOT_FOUND);
    });

    it('returns a JSON server error when handler returns no base_uri', function () {
        $request = Request::create('/no-base-uri/test', 'GET');
        $route = (new Route(['GET'], '/no-base-uri/{path?}', []))
            ->defaults('_passage_handler', TestNoBaseUriPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        Http::shouldReceive('withOptions')->never();

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(ResponseCode::HTTP_INTERNAL_SERVER_ERROR);
        expect(json_decode($response->getContent(), true))->toBe([
            'error' => "Passage handler [".TestNoBaseUriPassageController::class."] must return a 'base_uri' from getOptions().",
        ]);
    });

    it('proxies a basic GET request and returns JSON response', function () {
        $request = Request::create('/github/users/123', 'GET');
        $route = (new Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = jsonUpstreamResponse(['id' => 123]);

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

    it('strips sensitive client headers before passing to the handler', function () {
        $request = Request::create('/github/users', 'GET');
        $request->headers->set('Authorization', 'Bearer client-token');
        $request->headers->set('Cookie', 'session=abc');

        $route = (new Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestRequestOnlyPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = jsonUpstreamResponse(['user' => 'morcen']);

        // The handler (TestRequestOnlyPassageController) sets Authorization to a service token.
        // The original client cookie must not survive.
        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req) {
                return $req->header('Authorization') === 'Bearer injected-token'
                    && $req->header('Cookie') === null;
            })
            ->once()
            ->andReturn($mockResponse);

        $this->controller->handle($request);
    });

    it('extracts the path route parameter as the forwarded URI', function () {
        $request = Request::create('/github/users/morcen/repos', 'GET');
        $route = (new Route(['GET'], '/github/{path?}', []))
            ->defaults('_passage_handler', TestPassageController::class)
            ->where('path', '.*');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = jsonUpstreamResponse([]);

        $this->mockPassageService->shouldReceive('callService')
            ->withArgs(function (Request $req, $pending, string $uri) {
                return $uri === 'users/morcen/repos';
            })
            ->once()
            ->andReturn($mockResponse);

        $this->controller->handle($request);
    });

    it('applies response transformation', function () {
        $request = Request::create('/enriched/posts', 'GET');
        $route = (new Route(['GET'], '/enriched/{path?}', []))
            ->defaults('_passage_handler', TestResponseOnlyPassageController::class);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($mockPendingRequest);

        $mockResponse = jsonUpstreamResponse(['posts' => [1, 2, 3]]);

        $this->mockPassageService->shouldReceive('callService')->once()->andReturn($mockResponse);

        $response = $this->controller->handle($request);

        expect($response->getStatusCode())->toBe(201);
        expect($response->getData(true))->toMatchArray(['posts' => [1, 2, 3], 'response_enriched' => true]);
    });

    it('merges global passage options with handler options', function () {
        config(['passage.options' => ['timeout' => 60, 'http_errors' => false]]);

        $request = Request::create('/github/users', 'GET');
        $route = (new Route(['GET'], '/github/{path?}', []))
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

        $mockResponse = jsonUpstreamResponse([]);

        $this->mockPassageService->shouldReceive('callService')->once()->andReturn($mockResponse);

        $this->controller->handle($request);
    });

    describe('configurable sensitive header stripping', function () {
        it('strips headers listed in config strip_client_headers', function () {
            config(['passage.security.strip_client_headers' => ['x-internal-secret']]);

            $request = Request::create('/github/users', 'GET');
            $request->headers->set('X-Internal-Secret', 'do-not-forward');
            $request->headers->set('X-Safe-Header', 'forward-me');

            $route = (new Route(['GET'], '/github/{path?}', []))
                ->defaults('_passage_handler', TestPassageController::class);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);

            Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));

            $this->mockPassageService->shouldReceive('callService')
                ->withArgs(function (Request $req) {
                    return $req->header('X-Internal-Secret') === null
                        && $req->header('X-Safe-Header') === 'forward-me';
                })
                ->once()
                ->andReturn(jsonUpstreamResponse([]));

            $this->controller->handle($request);
        });
    });

    describe('PassageRequestAbortedException', function () {
        it('returns a formatted JSON error when handler throws PassageRequestAbortedException', function () {
            // Inline handler that aborts
            $abortingHandler = new class implements PassageControllerInterface
            {
                public function getRequest(Request $request): Request
                {
                    throw new PassageRequestAbortedException('Access denied.', 403);
                }

                public function getResponse(Request $request, Response $response): Response
                {
                    return $response;
                }

                public function getOptions(): array
                {
                    return ['base_uri' => 'https://api.example.com/'];
                }
            };

            // Register the anonymous class name dynamically
            $handlerClass = get_class($abortingHandler);

            $request = Request::create('/proxy/resource', 'GET');
            $route = (new Route(['GET'], '/proxy/{path?}', []))
                ->defaults('_passage_handler', $handlerClass);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);

            Http::shouldReceive('withOptions')->once()->andReturn(Mockery::mock(PendingRequest::class));
            $this->mockPassageService->shouldReceive('callService')->never();

            $response = $this->controller->handle($request);

            expect($response->getStatusCode())->toBe(403);
            expect($response->getData(true))->toBe(['error' => 'Access denied.']);
        });
    });
});
