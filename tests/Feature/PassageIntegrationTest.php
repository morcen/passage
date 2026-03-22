<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\Facades\Passage;
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

describe('Passage Integration Tests', function () {
    beforeEach(function () {
        config(['passage.enabled' => true]);

        $mockService = Mockery::mock(PassageServiceInterface::class);
        $this->app->instance(PassageServiceInterface::class, $mockService);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn(['ok' => true]);
        $mockResponse->shouldReceive('status')->andReturn(200);

        $mockService->shouldReceive('callService')->andReturn($mockResponse);
    });

    it('registers a GET route and proxies requests', function () {
        Passage::get('example/{path?}', IntegrationTestPassageController::class);

        $this->get('/example/users/1')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    });

    it('registers a POST route and proxies requests', function () {
        Passage::post('example/{path?}', IntegrationTestPassageController::class);

        $this->post('/example/items', ['name' => 'test'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    });

    it('returns 404 for a passage route with a missing handler', function () {
        // Register a route without a proper handler via raw route (edge case)
        \Illuminate\Support\Facades\Route::get('broken/{path?}', [
            \Morcen\Passage\Http\Controllers\PassageController::class, 'handle',
        ])->where('path', '.*');

        $this->get('/broken/anything')
            ->assertStatus(404)
            ->assertJson(['error' => 'Route not found']);
    });

    it('passage routes appear in the route collection', function () {
        Passage::get('listed/{path?}', IntegrationTestPassageController::class);

        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'listed'));

        expect($routes)->not->toBeEmpty();
    });
});
