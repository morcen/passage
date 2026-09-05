<?php

namespace Morcen\Passage\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;

/**
 * Single source of truth for discovering Passage routes and resolving their
 * handlers, shared by PassageController, PassageHealthCommand, and
 * PassageListCommand. Keeping this logic in one place prevents those three
 * call sites from drifting apart on how a handler is matched, validated, or
 * resolved (as previously happened between #211 and #212).
 */
class PassageRouteRegistry
{
    public function __construct(protected readonly Application $app) {}

    /**
     * All routes registered through Passage::get()/post()/etc., i.e. every
     * route dispatched to PassageController::handle().
     *
     * @return Collection<int, Route>
     */
    public function routes(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route) => str_contains(
                (string) $route->getActionName(),
                PassageController::class.'@handle'
            ));
    }

    /**
     * The Passage handler class configured on a route, if any.
     */
    public function handlerClassFor(Route $route): ?string
    {
        return $route->defaults['_passage_handler'] ?? null;
    }

    /**
     * Whether $handler names a class that can actually be resolved as a
     * Passage handler.
     */
    public function isValidHandler(?string $handler): bool
    {
        return $handler !== null
            && class_exists($handler)
            && is_subclass_of($handler, PassageControllerInterface::class);
    }

    /**
     * Resolve a handler class through the container, so constructor
     * dependencies are honoured the same way everywhere.
     */
    public function resolveHandler(string $handler): PassageControllerInterface
    {
        return $this->app->make($handler);
    }

    /**
     * Merge the package-wide default options with the handler's own,
     * letting the handler override any default.
     *
     * @return array<string, mixed>
     */
    public function optionsFor(PassageControllerInterface $handler): array
    {
        return array_merge(config('passage.options', []), $handler->getOptions());
    }
}
