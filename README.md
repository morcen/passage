# Passage: Lightweight API proxy gateway for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)

## Introduction

Passage is a lightweight API gateway package for Laravel that proxies incoming requests to external services. It gives you per-route control over HTTP method, path, request transformation, and response transformation — using a routing syntax that mirrors Laravel's own.

## Why developers use Passage

Passage is for Laravel apps that need to sit in front of one or more external APIs and expose them through your own application routes.

It is especially useful when you want to:

- Keep frontend or client apps talking to your Laravel app instead of directly to third-party APIs
- Centralize headers, tokens, request shaping, and response shaping in one place
- Add Laravel middleware, route groups, authentication, or rate limiting around upstream API calls
- Hide upstream API structure from consumers so you can change providers later with less surface-area impact
- Build a thin backend-for-frontend layer without writing the same HTTP plumbing over and over

In practice, Passage helps when your app needs a controlled proxy layer, not a full API management platform. You define proxy routes like normal Laravel routes, then customize how each request is forwarded and how each upstream response is returned.

### Passage is a good fit when

- You need a simple API proxy inside an existing Laravel app
- You want route-level control over how requests are forwarded
- You need to inject auth credentials or normalize payloads before calling an upstream service
- You want to reuse Laravel's routing and middleware system instead of introducing a separate gateway product

### Passage is probably not the right fit when

- Your app calls external APIs only from internal service classes or jobs and does not need inbound proxy routes
- You need a full enterprise API gateway with dashboards, analytics, service discovery, advanced policies, or traffic orchestration
- You need complex multi-service aggregation, retries, circuit breakers, or workflow logic as a first-class feature
- You want a general-purpose HTTP client wrapper rather than a request proxy layer

If you are building a Laravel app that needs to expose a stable, app-owned endpoint in front of external APIs, Passage gives you a lightweight and Laravel-native way to do that.

If you want to see how this maps to real projects, read the [example scenarios](example-scenarios/README.md).

## Features

- Route-based proxy definitions using a familiar `Passage::get/post/...` API
- Per-route request and response transformation hooks
- Global and per-handler Guzzle options (timeout, headers, etc.)
- Works naturally with Laravel route groups, middleware, named routes, and `route:list`
- Secure by default: sensitive client headers stripped before forwarding
- Auth helper traits: Bearer token, API key, HMAC signing
- Inbound request validation via Laravel's built-in validator
- Response caching for GET/HEAD routes
- Automatic retry with configurable backoff
- Streaming response support for large payloads
- Laravel event hooks around every proxy call
- Connectivity health check via `passage:health`

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x

> **Upgrading from v2?**
> v3.0.0 is a breaking release. The config-based `services` array and `Route::passage()` macro have been removed. See the [Upgrading from v2](#upgrading-from-v2) section below.

> **On v1.x?**
> PHP 8.1 and Laravel 10.x are no longer supported as of v2.0.0. Use [v1.2.4](https://github.com/morcen/passage/releases/tag/v1.2.4) for older environments.

## Installation

```bash
composer require morcen/passage
```

Then publish the config file:
```bash
php artisan passage:install
```

To publish the controller stub for generating Passage handlers:
```bash
php artisan vendor:publish --tag=passage-stubs
```

## Usage

### Defining proxy routes

Passage routes are defined in your route files (e.g. `routes/web.php`) using the `Passage` facade. The syntax mirrors Laravel's own routing:

```php
use Morcen\Passage\Facades\Passage;

Passage::get('github/{path?}', GithubPassageController::class);
Passage::post('stripe/{path?}', StripePassageController::class);
Passage::any('payments/{path?}', PaymentsPassageController::class);
```

Each call registers a real Laravel route, so your proxy routes appear in `php artisan route:list` alongside your application's own routes.

The `{path?}` parameter captures the sub-path that is forwarded to the upstream service. For example:

```
GET /github/users/morcen  →  GET https://api.github.com/users/morcen
POST /stripe/charges      →  POST https://api.stripe.com/charges
```

All supported methods: `get`, `post`, `put`, `patch`, `delete`, `any`.

### Route groups

Passage routes work inside any Laravel route group:

```php
Route::prefix('v1')->middleware('auth')->group(function () {
    Passage::get('github/{path?}', GithubPassageController::class);
    Passage::post('stripe/{path?}', StripePassageController::class);
});
```

Named routes and other route chaining also work:

```php
Passage::get('github/{path?}', GithubPassageController::class)
    ->name('github.proxy')
    ->middleware('throttle:60,1');
```

### Creating a Passage handler

Every Passage route requires a handler class. Generate one with:

```bash
php artisan passage:controller GithubPassageController
```

This creates `app/Http/Controllers/Passages/GithubPassageController.php` extending `PassageHandler`:

```php
use Morcen\Passage\PassageHandler;

class GithubPassageController extends PassageHandler
{
    public function getOptions(): array
    {
        return [
            'base_uri' => 'https://api.github.com/',
        ];
    }
}
```

You only need to override the methods relevant to your handler. All three interface methods have no-op defaults in `PassageHandler`:

- `getOptions(): array` — upstream base URI and any Guzzle options
- `getRequest(Request $request): Request` — transform or add credentials before forwarding
- `getResponse(Request $request, Response $response): Response` — transform the upstream response

If you prefer to implement the interface directly without the base class, implement `PassageControllerInterface` instead.

> **Note:** The `base_uri` must end with a trailing slash `/`, otherwise sub-path forwarding may not work correctly.

### Global options

Timeout and connection settings that apply to all Passage routes can be configured in `config/passage.php` or via environment variables:

```env
PASSAGE_TIMEOUT=30
PASSAGE_CONNECT_TIMEOUT=10
```

Options defined in a handler's `getOptions()` override these global defaults.

### Listing proxy routes

```bash
php artisan passage:list
```

Displays a table of all registered Passage routes with their HTTP methods, URIs, and upstream targets.

### Disabling Passage

Set `PASSAGE_ENABLED=false` in your `.env` to disable all Passage proxying without removing route definitions:

```env
PASSAGE_ENABLED=false
```

---

## Security

### Header stripping

Passage strips sensitive client-origin headers before forwarding requests upstream. By default, `cookie`, `authorization`, and `proxy-authorization` are removed from every incoming request. This prevents client credentials from leaking to upstream services.

Handlers can re-add credentials from your own config inside `getRequest()`:

```php
public function getRequest(Request $request): Request
{
    $request->headers->set('Authorization', 'Bearer '.config('services.github.token'));
    return $request;
}
```

To change which headers are stripped globally, edit `config/passage.php`:

```php
'security' => [
    'strip_client_headers' => ['cookie', 'authorization', 'proxy-authorization'],
],
```

### Forwarding a client header on a specific route

If a route legitimately needs to forward a specific client header (for example, forwarding a client's `Authorization` to an upstream that validates it), implement `AcceptsClientHeaders` on the handler:

```php
use Morcen\Passage\Contracts\AcceptsClientHeaders;
use Morcen\Passage\PassageHandler;

class RelayHandler extends PassageHandler implements AcceptsClientHeaders
{
    public function allowedClientHeaders(): array
    {
        return ['authorization'];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.partner.com/'];
    }
}
```

The listed headers bypass the strip policy only for that handler. All other handlers continue to strip them.

### Allowed hosts guard

To prevent a misconfigured handler from proxying to an unintended host, enable the allowed hosts guard:

```env
PASSAGE_ENFORCE_ALLOWED_HOSTS=true
```

Then list the permitted upstream hostnames in `config/passage.php`:

```php
'security' => [
    'enforce_allowed_hosts' => true,
    'allowed_hosts' => ['api.github.com', 'api.stripe.com'],
],
```

Any handler whose `base_uri` resolves to a host not in the list will throw `DisallowedProxyTargetException` instead of forwarding the request.

When enabled, the guard also re-validates every upstream redirect hop: if an allowed host responds with a redirect to a host outside `allowed_hosts`, Passage aborts the request with a 403 instead of following it. To opt a specific handler out of redirect-following entirely, set `'allow_redirects' => false` in its `getOptions()`.

### Aborting a request from a handler

To abort a request early with a specific HTTP status, throw `PassageRequestAbortedException` inside `getRequest()`:

```php
use Morcen\Passage\Exceptions\PassageRequestAbortedException;

public function getRequest(Request $request): Request
{
    if (! $this->isAllowed($request)) {
        throw new PassageRequestAbortedException('Access denied.', 403);
    }
    return $request;
}
```

Passage catches this exception and returns a JSON error response with the given status code.

### Inbound validation

To validate the incoming request before it is forwarded, implement `ValidatesInboundRequest` and declare Laravel validation rules:

```php
use Morcen\Passage\Contracts\ValidatesInboundRequest;
use Morcen\Passage\PassageHandler;

class CreateOrderHandler extends PassageHandler implements ValidatesInboundRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://orders.example.com/'];
    }
}
```

Validation runs before `getRequest()`. If it fails, a 422 response is returned and the upstream is never called.

### Rate limiting

Passage routes are real Laravel routes, so the built-in `throttle` middleware works directly:

```php
Passage::post('orders/{path?}', CreateOrderHandler::class)
    ->middleware('throttle:60,1');
```

---

## Auth helpers

`PassageHandler` includes three built-in auth traits. Use them inside `getRequest()` to inject credentials:

### Bearer token

```php
use Morcen\Passage\PassageHandler;

class GithubHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withBearerToken($request, config('services.github.token'));
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.github.com/'];
    }
}
```

Generate a handler pre-scaffolded for Bearer auth:

```bash
php artisan passage:controller GithubHandler --with-auth=bearer
```

### API key

```php
public function getRequest(Request $request): Request
{
    // Inject as a header (default: X-API-Key)
    return $this->withApiKey($request, config('services.stripe.key'));

    // Or inject as a query parameter
    return $this->withApiKeyQuery($request, config('services.stripe.key'), 'api_key');

    // Or use a custom header name
    return $this->withApiKey($request, config('services.stripe.key'), 'X-Stripe-Key');
}
```

```bash
php artisan passage:controller StripeHandler --with-auth=apikey
```

### HMAC signing

```php
public function getRequest(Request $request): Request
{
    return $this->withHmacSignature($request, config('services.partner.secret'));
}
```

This signs the timestamp, HTTP method, path, query string, and request body using HMAC-SHA256 and adds `X-Timestamp` and `X-Signature` headers to the outgoing request.

```bash
php artisan passage:controller PartnerHandler --with-auth=hmac
```

---

## Resilience

### Retry

Add automatic retry by returning `passage_retry_times` (and optionally `passage_retry_sleep_ms`) from `getOptions()`, or use the `withRetry()` helper from `HasResilienceOptions`:

```php
use Morcen\Passage\PassageHandler;

class PaymentsHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://payments.example.com/'],
            $this->withRetry(3, 200),
        );
    }
}
```

```bash
php artisan passage:controller PaymentsHandler --with-retry
```

`withRetry($times, $sleepMs, ?callable $when)` accepts an optional third argument — a callable that receives the exception and the pending request and returns `true` if the request should be retried:

```php
$this->withRetry(
    times: 3,
    sleepMs: 200,
    when: function (\Exception $e, \Illuminate\Http\Client\PendingRequest $request) {
        // Only retry on connection errors, not on 4xx responses
        return $e instanceof \Illuminate\Http\Client\ConnectionException;
    }
)
```

### Upstream error handling

Passage maps transport-layer failures to appropriate HTTP status codes automatically:

| Cause | Status |
|---|---|
| Connection refused / DNS failure | 502 Bad Gateway |
| Timeout | 504 Gateway Timeout |
| Too many redirects | 502 Bad Gateway |
| Unexpected exception | 500 Internal Server Error |

Upstream 4xx and 5xx responses are passed through unchanged.

---

## Caching

GET and HEAD responses can be cached per-route. Return `passage_cache_ttl` (seconds) from `getOptions()`:

```php
public function getOptions(): array
{
    return [
        'base_uri'          => 'https://api.example.com/',
        'passage_cache_ttl' => 60,
    ];
}
```

```bash
php artisan passage:controller ExampleHandler --with-cache
```

The cache store used defaults to Laravel's default cache driver. To use a specific store, set it in `config/passage.php`:

```php
'cache' => [
    'store' => 'redis',
],
```

Or via environment variable:

```env
PASSAGE_CACHE_STORE=redis
```

---

## Streaming

For large or long-running upstream responses, enable streaming so Passage does not buffer the full response body in memory:

```php
public function getOptions(): array
{
    return [
        'base_uri'          => 'https://files.example.com/',
        'passage_streaming' => true,
    ];
}
```

When streaming is enabled, the `getResponse()` transformation hook is skipped (the response body has not been read yet). The Content-Type and other upstream headers are still passed through.

`passage_cache_ttl` and `passage_streaming` cannot be combined on the same handler: the upstream body is a single-pass stream, and reading it once for a cache write would leave nothing for the client. If a handler sets both, caching is silently disabled and every request is streamed straight through — set only one of the two options per handler.

---

## Observability

### Events

Passage fires three Laravel events around every proxy call:

| Event | When |
|---|---|
| `PassageRequestSending` | Before the upstream call |
| `PassageResponseReceived` | After a successful response |
| `PassageRequestFailed` | After a transport error |

To log all Passage activity, register `PassageEventSubscriber` in your `EventServiceProvider`:

```php
use Morcen\Passage\Listeners\PassageEventSubscriber;

protected $subscribe = [
    PassageEventSubscriber::class,
];
```

This subscriber logs to a `passage` channel at `info` level (request/response) and `error` level (failures).

To disable events:

```env
PASSAGE_EVENTS=false
```

### Health check

Ping the `base_uri` of every registered Passage route and see connectivity status:

```bash
php artisan passage:health
```

Useful in CI pipelines and post-deployment checks. Use `--timeout=10` to adjust the per-route probe timeout (default: 5 seconds).

---

## Production checklist

Before deploying Passage in production:

- [ ] Set `PASSAGE_ENFORCE_ALLOWED_HOSTS=true` and list all permitted upstream hosts in `config/passage.php`
- [ ] Confirm that sensitive headers (`cookie`, `authorization`) are NOT being forwarded unless intentional (check `strip_client_headers`)
- [ ] Apply `throttle` middleware to any publicly accessible Passage routes
- [ ] Set `PASSAGE_TIMEOUT` and `PASSAGE_CONNECT_TIMEOUT` appropriate for your upstream services (default: 30s / 10s)
- [ ] Enable retry (`passage_retry_times`) for routes calling unreliable upstream services
- [ ] Enable caching (`passage_cache_ttl`) for high-traffic read-only routes
- [ ] Register `PassageEventSubscriber` and configure a `passage` log channel for observability
- [ ] Run `php artisan passage:health` as a post-deployment check

---

## Upgrading from v2

v3.0.0 is a **breaking release**. If you are on v2 and are not ready to migrate, pin your version in `composer.json`:

```json
"morcen/passage": "^2.0"
```

### What changed

| v2 | v3 |
|----|----|
| `config/passage.php` `services` array | Removed — routes are defined in route files |
| `Route::passage()` in `routes/web.php` | Removed — use `Passage::get/post/...` instead |
| Array-based handlers (`['base_uri' => '...']`) | Removed — a handler class is always required |

### Migration steps

**1. Remove `Route::passage()` from your route files.**

**2. For each entry in `config/passage.php` `services`:**

If the entry was an array:
```php
// v2 config/passage.php
'github' => ['base_uri' => 'https://api.github.com/'],
```

Create a handler class (or use `passage:controller`) and move `base_uri` into `getOptions()`:
```php
// v3 app/Http/Controllers/Passages/GithubPassageController.php
public function getOptions(): array
{
    return ['base_uri' => 'https://api.github.com/'];
}
```

If the entry was already a controller class, it can be reused as-is — just make sure it implements `PassageControllerInterface`.

**3. Register routes in your route files:**
```php
// v3 routes/web.php
use Morcen\Passage\Facades\Passage;

Passage::get('github/{path?}', GithubPassageController::class);
```

**4. Remove the `services` key from `config/passage.php`** (or re-publish the config with `php artisan vendor:publish --tag=passage-config --force`).

---

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Morcen Chavez](https://github.com/morcen)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
