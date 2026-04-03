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
- Authentication and authorization handling (coming soon)
- Rate limiting and throttling (coming soon)
- Caching and response caching (coming soon)

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

Every Passage route requires a handler class that implements `PassageControllerInterface`. Generate one with:

```bash
php artisan passage:controller GithubPassageController
```

This creates `app/Http/Controllers/Passages/GithubPassageController.php`. Implement the three required methods:

```php
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\PassageControllerInterface;

class GithubPassageController implements PassageControllerInterface
{
    /**
     * The upstream base URI and any Guzzle options for this service.
     * See https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function getOptions(): array
    {
        return [
            'base_uri' => 'https://api.github.com/',
        ];
    }

    /**
     * Transform or validate the request before it is forwarded upstream.
     * Return the request unchanged to pass it through as-is.
     */
    public function getRequest(Request $request): Request
    {
        // Example: inject an auth token
        $request->headers->set('Authorization', 'Bearer '.config('services.github.token'));
        return $request;
    }

    /**
     * Transform or validate the upstream response before it is returned to the client.
     * Return the response unchanged to pass it through as-is.
     */
    public function getResponse(Request $request, Response $response): Response
    {
        return $response;
    }
}
```

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
