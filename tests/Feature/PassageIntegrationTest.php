<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;

class IntegrationTestPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class XmlIntegrationPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://xml.example.com/'];
    }
}

class PlainTextIntegrationPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://text.example.com/'];
    }
}

/**
 * Build a mock upstream Response for PassageResponseBuilder to consume.
 */
function integrationResponse(string $body, int $status, string $contentType): Response
{
    $mock = Mockery::mock(Response::class);
    $mock->shouldReceive('status')->andReturn($status);
    $mock->shouldReceive('body')->andReturn($body);
    $mock->shouldReceive('header')->with('Content-Type')->andReturn($contentType);
    $mock->shouldReceive('headers')->andReturn(['content-type' => [$contentType]]);
    if (str_contains($contentType, 'application/json')) {
        $mock->shouldReceive('json')->andReturn(json_decode($body, true));
    }

    return $mock;
}

describe('Passage Integration Tests', function () {
    beforeEach(function () {
        config(['passage.enabled' => true]);

        $this->mockService = Mockery::mock(PassageServiceInterface::class);
        $this->app->instance(PassageServiceInterface::class, $this->mockService);
    });

    it('registers a GET route and proxies JSON requests', function () {
        Passage::get('example/{path?}', IntegrationTestPassageController::class);

        $this->mockService->shouldReceive('callService')
            ->andReturn(integrationResponse('{"ok":true}', 200, 'application/json'));

        $this->get('/example/users/1')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    });

    it('registers a POST route and proxies JSON requests', function () {
        Passage::post('example/{path?}', IntegrationTestPassageController::class);

        $this->mockService->shouldReceive('callService')
            ->andReturn(integrationResponse('{"created":true}', 201, 'application/json'));

        $this->post('/example/items', ['name' => 'test'])
            ->assertStatus(201)
            ->assertJson(['created' => true]);
    });

    it('passes through XML response with correct Content-Type', function () {
        Passage::get('xml/{path?}', XmlIntegrationPassageController::class);

        $xmlBody = '<?xml version="1.0"?><root><id>1</id></root>';
        $this->mockService->shouldReceive('callService')
            ->andReturn(integrationResponse($xmlBody, 200, 'application/xml'));

        $response = $this->get('/xml/feed');
        $response->assertStatus(200);
        expect($response->headers->get('Content-Type'))->toContain('application/xml');
        expect($response->getContent())->toBe($xmlBody);
    });

    it('passes through plain text response', function () {
        Passage::get('text/{path?}', PlainTextIntegrationPassageController::class);

        $this->mockService->shouldReceive('callService')
            ->andReturn(integrationResponse('pong', 200, 'text/plain'));

        $response = $this->get('/text/ping');
        $response->assertStatus(200);
        expect($response->getContent())->toBe('pong');
    });

    it('returns 404 for a passage route with a missing handler', function () {
        Route::get('broken/{path?}', [
            PassageController::class, 'handle',
        ])->where('path', '.*');

        $this->get('/broken/anything')
            ->assertStatus(404)
            ->assertJson(['error' => 'Route not found']);
    });

    it('passage routes appear in the route collection', function () {
        Passage::get('listed/{path?}', IntegrationTestPassageController::class);

        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'listed'));

        expect($routes)->not->toBeEmpty();
    });
});
