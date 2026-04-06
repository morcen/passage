<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Contracts\AcceptsClientHeaders;
use Morcen\Passage\Contracts\ValidatesInboundRequest;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageHandler;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class IntegrationTestPassageController extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class XmlIntegrationPassageController extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://xml.example.com/'];
    }
}

class PlainTextIntegrationPassageController extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'https://text.example.com/'];
    }
}

class AcceptsAuthHeaderHandler extends PassageHandler implements AcceptsClientHeaders
{
    public function allowedClientHeaders(): array
    {
        return ['authorization'];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://relay.example.com/'];
    }
}

class AcceptsMixedCaseAuthHeaderHandler extends PassageHandler implements AcceptsClientHeaders
{
    public function allowedClientHeaders(): array
    {
        return ['Authorization'];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://relay.example.com/'];
    }
}

class ValidatingHandler extends PassageHandler implements ValidatesInboundRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class NoBaseUriHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return [];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Passage Integration Tests', function () {
    beforeEach(function () {
        config(['passage.enabled' => true, 'passage.events.enabled' => false]);
    });

    describe('JSON proxying', function () {
        it('registers a GET route and proxies JSON requests', function () {
            Http::fake(['https://api.example.com/*' => Http::response(['ok' => true], 200)]);
            Passage::get('example/{path?}', IntegrationTestPassageController::class);

            $this->get('/example/users/1')
                ->assertStatus(200)
                ->assertJson(['ok' => true]);

            Http::assertSent(fn ($req) => $req->url() === 'https://api.example.com/users/1');
        });

        it('registers a POST route and proxies JSON requests', function () {
            Http::fake(['https://api.example.com/*' => Http::response(['created' => true], 201)]);
            Passage::post('example/{path?}', IntegrationTestPassageController::class);

            $this->postJson('/example/items', ['name' => 'widget'])
                ->assertStatus(201)
                ->assertJson(['created' => true]);

            Http::assertSent(fn ($req) => $req->method() === 'POST');
        });
    });

    describe('non-JSON response passthrough', function () {
        it('passes through XML response with correct Content-Type', function () {
            $xml = '<?xml version="1.0"?><root><id>1</id></root>';
            Http::fake(['https://xml.example.com/*' => Http::response($xml, 200, ['Content-Type' => 'application/xml'])]);
            Passage::get('xml/{path?}', XmlIntegrationPassageController::class);

            $response = $this->get('/xml/feed');
            $response->assertStatus(200);
            expect($response->headers->get('Content-Type'))->toContain('application/xml');
            expect($response->getContent())->toBe($xml);
        });

        it('passes through plain text response', function () {
            Http::fake(['https://text.example.com/*' => Http::response('pong', 200, ['Content-Type' => 'text/plain'])]);
            Passage::get('text/{path?}', PlainTextIntegrationPassageController::class);

            $response = $this->get('/text/ping');
            $response->assertStatus(200);
            expect($response->getContent())->toBe('pong');
        });
    });

    describe('AcceptsClientHeaders', function () {
        it('preserves allowed client headers from the strip policy', function () {
            Http::fake(['https://relay.example.com/*' => Http::response(['ok' => true], 200)]);
            Passage::get('relay/{path?}', AcceptsAuthHeaderHandler::class);

            $this->withHeaders(['Authorization' => 'Bearer client-token'])
                ->get('/relay/resource')
                ->assertStatus(200);

            // The Authorization header should have been forwarded upstream
            Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer client-token'));
        });

        it('matches allowed client headers case-insensitively', function () {
            Http::fake(['https://relay.example.com/*' => Http::response(['ok' => true], 200)]);
            Passage::get('relay-mixed-case/{path?}', AcceptsMixedCaseAuthHeaderHandler::class);

            $this->withHeaders(['Authorization' => 'Bearer client-token'])
                ->get('/relay-mixed-case/resource')
                ->assertStatus(200);

            Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer client-token'));
        });
    });

    describe('ValidatesInboundRequest', function () {
        it('returns 422 when validation fails before forwarding', function () {
            Http::fake(); // Should not be called
            Passage::post('validated/{path?}', ValidatingHandler::class);

            $this->postJson('/validated/items', [])
                ->assertStatus(422);

            Http::assertNothingSent();
        });

        it('forwards request when validation passes', function () {
            Http::fake(['https://api.example.com/*' => Http::response(['ok' => true], 200)]);
            Passage::post('validated/{path?}', ValidatingHandler::class);

            $this->postJson('/validated/items', ['name' => 'widget'])
                ->assertStatus(200);

            Http::assertSent(fn ($req) => $req->method() === 'POST');
        });
    });

    describe('route registration', function () {
        it('returns a JSON server error when a handler omits base_uri', function () {
            Passage::get('missing-base-uri/{path?}', NoBaseUriHandler::class);

            $this->withExceptionHandling()
                ->get('/missing-base-uri/resource')
                ->assertStatus(500)
                ->assertJson([
                    'error' => "Passage handler [NoBaseUriHandler] must return a 'base_uri' from getOptions().",
                ]);
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
});
