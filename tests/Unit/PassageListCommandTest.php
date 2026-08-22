<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\PassageControllerInterface;

class ListCommandTestPassageController implements PassageControllerInterface
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
        return ['base_uri' => 'https://api.github.com'];
    }
}

class ListCommandDependency
{
    public function baseUri(): string
    {
        return 'https://api.example.com';
    }
}

class DependentListCommandPassageController implements PassageControllerInterface
{
    public function __construct(private ListCommandDependency $dependency) {}

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
        return ['base_uri' => $this->dependency->baseUri()];
    }
}

class ThrowingListCommandPassageController implements PassageControllerInterface
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
        throw new RuntimeException('boom');
    }
}

// An app-namespaced controller that happens to share the short class name
// "PassageController" with Morcen\Passage\Http\Controllers\PassageController,
// including its own unrelated "handle" method. Used to prove passage:list
// only matches the package's fully-qualified controller, not a same-named
// class defined by the host application.
class PassageController
{
    public function handle(): string
    {
        return 'not a Passage route';
    }
}

describe('PassageListCommand', function () {
    it('warns when passage is disabled', function () {
        config()->set('passage.enabled', false);

        $this->artisan('passage:list')
            ->expectsOutputToContain('Passage is disabled')
            ->assertExitCode(0);
    });

    it('warns when no Passage routes are registered', function () {
        config()->set('passage.enabled', true);

        $this->artisan('passage:list')
            ->expectsOutputToContain('No Passage routes registered')
            ->assertExitCode(0);
    });

    it('lists registered Passage routes', function () {
        config()->set('passage.enabled', true);

        Passage::get('github/{path?}', ListCommandTestPassageController::class);

        $this->artisan('passage:list')
            ->expectsTable(
                ['Method', 'URI', 'Target'],
                [['GET|HEAD', 'github/{path?}', 'https://api.github.com ('.ListCommandTestPassageController::class.')']]
            )
            ->assertExitCode(0);
    });

    it('shows multiple registered routes', function () {
        config()->set('passage.enabled', true);

        Passage::get('github/{path?}', ListCommandTestPassageController::class);
        Passage::post('github/{path?}', ListCommandTestPassageController::class);

        $this->artisan('passage:list')
            ->expectsOutputToContain('github/{path?}')
            ->assertExitCode(0);
    });

    it('resolves handlers with constructor dependencies through the container', function () {
        config()->set('passage.enabled', true);

        Passage::get('dependent/{path?}', DependentListCommandPassageController::class);

        $this->artisan('passage:list')
            ->expectsTable(
                ['Method', 'URI', 'Target'],
                [['GET|HEAD', 'dependent/{path?}', 'https://api.example.com ('.DependentListCommandPassageController::class.')']]
            )
            ->assertExitCode(0);
    });

    it('does not list an app route whose controller merely shares the short class name "PassageController"', function () {
        config()->set('passage.enabled', true);

        Passage::get('github/{path?}', ListCommandTestPassageController::class);
        Route::get('unrelated/{path?}', [PassageController::class, 'handle']);

        $this->artisan('passage:list')
            ->expectsTable(
                ['Method', 'URI', 'Target'],
                [['GET|HEAD', 'github/{path?}', 'https://api.github.com ('.ListCommandTestPassageController::class.')']]
            )
            ->assertExitCode(0);
    });

    it('continues listing routes when resolving a handler target throws', function () {
        config()->set('passage.enabled', true);

        Passage::get('broken-target/{path?}', ThrowingListCommandPassageController::class);

        $this->artisan('passage:list')
            ->expectsTable(
                ['Method', 'URI', 'Target'],
                [[
                    'GET|HEAD',
                    'broken-target/{path?}',
                    ThrowingListCommandPassageController::class.' (could not resolve target)',
                ]]
            )
            ->assertExitCode(0);
    });
});
