<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Support\PassageRouteRegistry;

class RegistryTestPassageController implements PassageControllerInterface
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

class RegistryTestDependency
{
    public function baseUri(): string
    {
        return 'https://dependency.example.com';
    }
}

class DependentRegistryTestPassageController implements PassageControllerInterface
{
    public function __construct(private RegistryTestDependency $dependency) {}

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

describe('PassageRouteRegistry::routes()', function () {
    it('returns only routes registered through the Passage facade', function () {
        Passage::get('github/{path?}', RegistryTestPassageController::class);
        Route::get('unrelated/{path?}', fn () => 'not a Passage route');

        $routes = app(PassageRouteRegistry::class)->routes();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->uri())->toBe('github/{path?}');
    });

    it('returns an empty collection when no Passage routes are registered', function () {
        expect(app(PassageRouteRegistry::class)->routes())->toBeEmpty();
    });
});

describe('PassageRouteRegistry::handlerClassFor()', function () {
    it('reads the _passage_handler route default', function () {
        $route = Passage::get('github/{path?}', RegistryTestPassageController::class);

        expect(app(PassageRouteRegistry::class)->handlerClassFor($route))
            ->toBe(RegistryTestPassageController::class);
    });

    it('returns null when the route has no _passage_handler default', function () {
        $route = Route::get('unrelated/{path?}', fn () => 'not a Passage route');

        expect(app(PassageRouteRegistry::class)->handlerClassFor($route))->toBeNull();
    });
});

describe('PassageRouteRegistry::isValidHandler()', function () {
    it('is true for a class implementing PassageControllerInterface', function () {
        expect(app(PassageRouteRegistry::class)->isValidHandler(RegistryTestPassageController::class))
            ->toBeTrue();
    });

    it('is false for null', function () {
        expect(app(PassageRouteRegistry::class)->isValidHandler(null))->toBeFalse();
    });

    it('is false for a class that does not exist', function () {
        expect(app(PassageRouteRegistry::class)->isValidHandler('App\\Handlers\\DeletedPassageController'))
            ->toBeFalse();
    });

    it('is false for a class that does not implement PassageControllerInterface', function () {
        expect(app(PassageRouteRegistry::class)->isValidHandler(RegistryTestDependency::class))
            ->toBeFalse();
    });
});

describe('PassageRouteRegistry::resolveHandler()', function () {
    it('resolves a handler with constructor dependencies through the container', function () {
        $handler = app(PassageRouteRegistry::class)->resolveHandler(DependentRegistryTestPassageController::class);

        expect($handler)->toBeInstanceOf(DependentRegistryTestPassageController::class);
        expect($handler->getOptions())->toBe(['base_uri' => 'https://dependency.example.com']);
    });
});

describe('PassageRouteRegistry::optionsFor()', function () {
    it('merges config(passage.options) with the handler own options', function () {
        config()->set('passage.options', ['timeout' => 5, 'base_uri' => 'https://default.example.com']);

        $handler = new RegistryTestPassageController;
        $options = app(PassageRouteRegistry::class)->optionsFor($handler);

        expect($options)->toBe([
            'timeout' => 5,
            // The handler's own base_uri wins over the configured default.
            'base_uri' => 'https://api.example.com',
        ]);
    });
});
