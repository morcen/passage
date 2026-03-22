<?php

use Illuminate\Routing\Route;
use Morcen\Passage\Passage;

beforeEach(function () {
    $this->passage = new Passage;
});

describe('Passage', function () {
    it('get() registers a GET route pointing to PassageController@handle', function () {
        $route = $this->passage->get('github/{path?}', 'SomeHandler');

        expect($route)->toBeInstanceOf(Route::class);
        expect($route->methods())->toContain('GET');
        expect($route->getAction('uses'))->toContain('PassageController@handle');
        expect($route->defaults['_passage_handler'])->toBe('SomeHandler');
    });

    it('post() registers a POST route', function () {
        $route = $this->passage->post('stripe/{path?}', 'SomeHandler');

        expect($route->methods())->toContain('POST');
        expect($route->methods())->not->toContain('GET');
    });

    it('put() registers a PUT route', function () {
        $route = $this->passage->put('resource/{path?}', 'SomeHandler');

        expect($route->methods())->toContain('PUT');
    });

    it('patch() registers a PATCH route', function () {
        $route = $this->passage->patch('resource/{path?}', 'SomeHandler');

        expect($route->methods())->toContain('PATCH');
    });

    it('delete() registers a DELETE route', function () {
        $route = $this->passage->delete('resource/{path?}', 'SomeHandler');

        expect($route->methods())->toContain('DELETE');
    });

    it('any() registers a route for all HTTP methods', function () {
        $route = $this->passage->any('payments/{path?}', 'SomeHandler');

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            expect($route->methods())->toContain($method);
        }
    });

    it('stores the handler class in route defaults', function () {
        $route = $this->passage->get('github/{path?}', 'App\\Http\\Controllers\\GithubPassageController');

        expect($route->defaults['_passage_handler'])->toBe('App\\Http\\Controllers\\GithubPassageController');
    });

    it('returned route can be chained with name()', function () {
        $route = $this->passage->get('github/{path?}', 'SomeHandler')->name('github.proxy');

        expect($route->getName())->toBe('github.proxy');
    });
});
