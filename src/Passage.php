<?php

namespace Morcen\Passage;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Morcen\Passage\Http\Controllers\PassageController;

class Passage
{
    public function get(string $uri, string $handler): Route
    {
        return $this->register(['GET', 'HEAD'], $uri, $handler);
    }

    public function post(string $uri, string $handler): Route
    {
        return $this->register(['POST'], $uri, $handler);
    }

    public function put(string $uri, string $handler): Route
    {
        return $this->register(['PUT'], $uri, $handler);
    }

    public function patch(string $uri, string $handler): Route
    {
        return $this->register(['PATCH'], $uri, $handler);
    }

    public function delete(string $uri, string $handler): Route
    {
        return $this->register(['DELETE'], $uri, $handler);
    }

    public function any(string $uri, string $handler): Route
    {
        return $this->register(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $handler);
    }

    private function register(array $methods, string $uri, string $handler): Route
    {
        return RouteFacade::match($methods, $uri, [PassageController::class, 'handle'])
            ->defaults('_passage_handler', $handler)
            ->where('path', '.*');
    }
}
