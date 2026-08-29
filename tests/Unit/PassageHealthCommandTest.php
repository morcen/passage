<?php

use Illuminate\Http\Client\ConnectionException;
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

class UnreachableHealthCommandPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://unreachable.example.com'];
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
            ->expectsOutputToContain('No base_uri configured')
            ->assertExitCode(1);
    });

    it('still checks other routes when a route has no base_uri configured', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('no-base-uri/{path?}', NoBaseUriHealthCommandPassageController::class);
        Passage::post('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('No base_uri configured')
            ->expectsOutputToContain('OK')
            ->assertExitCode(1);
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

    it('fails when a route has a missing or invalid handler class', function () {
        config()->set('passage.enabled', true);

        Passage::get('missing-handler/{path?}', 'App\\Handlers\\DeletedPassageController');

        $this->artisan('passage:health')
            ->expectsOutputToContain('Invalid or missing handler class')
            ->assertExitCode(1);
    });

    it('still checks other routes when a route has a missing handler class', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('missing-handler/{path?}', 'App\\Handlers\\DeletedPassageController');
        Passage::post('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('FAIL')
            ->expectsOutputToContain('OK')
            ->assertExitCode(1);
    });

    it('falls back to the default timeout and warns when --timeout is zero', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health', ['--timeout' => '0'])
            ->expectsOutputToContain('Invalid --timeout value')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    });

    it('falls back to the default timeout and warns when --timeout is non-numeric', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health', ['--timeout' => 'abc'])
            ->expectsOutputToContain('Invalid --timeout value')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    });

    it('falls back to the default timeout and warns when --timeout is negative', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health', ['--timeout' => '-5'])
            ->expectsOutputToContain('Invalid --timeout value')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    });

    it('accepts a valid positive --timeout without warning', function () {
        config()->set('passage.enabled', true);

        Http::fake(['https://api.example.com' => Http::response('', 200)]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health', ['--timeout' => '10'])
            ->doesntExpectOutputToContain('Invalid --timeout value')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    });

    it('fails when the HTTP probe itself throws for an unreachable host', function () {
        config()->set('passage.enabled', true);

        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        Passage::get('unreachable/{path?}', HealthCommandTestPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('refused')
            ->assertExitCode(1);
    });

    it('still reports OK for other routes when one route\'s probe throws', function () {
        config()->set('passage.enabled', true);

        Http::fake([
            'https://api.example.com' => Http::response('', 200),
            'https://unreachable.example.com' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        Passage::get('healthy/{path?}', HealthCommandTestPassageController::class);
        Passage::post('unreachable/{path?}', UnreachableHealthCommandPassageController::class);

        $this->artisan('passage:health')
            ->expectsOutputToContain('OK')
            ->expectsOutputToContain('refused')
            ->assertExitCode(1);
    });
});
