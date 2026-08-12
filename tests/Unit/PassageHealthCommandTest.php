<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\PassageControllerInterface;

class HealthCommandTestPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://api.example.com'];
    }
}

class NoBaseUriHealthCommandPassageController implements PassageControllerInterface
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

class ThrowingHealthCommandPassageController implements PassageControllerInterface
{
    public function __construct()
    {
        throw new RuntimeException('unresolvable dependency');
    }

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
        return ['base_uri' => 'https://api.example.com'];
    }
}

describe('PassageHealthCommand', function () {
    it('warns when passage is disabled', function () {
        config()->set('passage.enabled', false);

        $this->artisan('passage:health')
            ->expectsOutputToContain('Passage is disabled')
            ->assertExitCode(0);
    });

    it('warns when no Passage routes are registered', function () {
        config()->set('passage.enabled', true);

        $this->artisan('passage:health')
            ->expectsOutputToContain('No Passage routes are registered')
            ->assertExitCode(0);
    });

    it('reports OK for a reachable route', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    });

    it('fails when a route has no base_uri configured', function () {
        config()->set('passage.enabled', true);

        Passage::get('no-base-uri/{path?}', NoBaseUriHealthCommandPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('SKIPPED')
            ->assertExitCode(0);
    });

    it('continues checking other routes when a handler cannot be resolved', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('broken/{path?}', ThrowingHealthCommandPassageController::class);
        Passage::post('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('Could not resolve handler: unresolvable dependency')
            ->expectsOutputToContain('OK')
            ->assertExitCode(1);
    });

    it('reports failure when a handler cannot be resolved even if no other routes exist', function () {
        config()->set('passage.enabled', true);

        Passage::get('broken/{path?}', ThrowingHealthCommandPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain(ThrowingHealthCommandPassageController::class)
            ->assertExitCode(1);
    });
});
