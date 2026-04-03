# Example Scenarios

This guide shows real-world situations where Passage can be useful and how to implement it in a Laravel-friendly way.

The goal is not to show every possible architecture. It is to help you quickly recognize, "Yes, this is the kind of problem Passage can solve for me."

## Before you choose Passage

Passage works best when:

- You want Laravel to be the public-facing entrypoint for one or more upstream APIs
- You want to define proxy behavior per route or per service
- You need to inject headers, tokens, validation, or response shaping before returning upstream responses
- You want to keep using Laravel routing, middleware, auth, and rate limiting

Passage is less suitable when:

- You only need to call external APIs from internal Laravel services or jobs
- You need a full API gateway platform with advanced traffic policies, dashboards, and service discovery
- You need to aggregate multiple upstream services into one response as the main use case

## Scenario 1: Protect external APIs behind your Laravel app

### Situation

You do not want your frontend, mobile app, or partner integrations to call third-party APIs directly.

You want clients to call your Laravel app instead:

- `GET /api/transactions/1`
- `POST /api/payments`

Your Laravel app then forwards those requests to the correct upstream service.

### Why Passage helps

Passage gives you a controlled proxy layer inside Laravel. You can:

- keep API tokens and upstream URLs on the server
- apply middleware before requests leave your app
- normalize requests and responses so clients see a stable contract
- replace or reorganize upstream providers later without changing public endpoints

### Best way to implement it

Use explicit routes per service instead of a single open-ended dynamic proxy route.

Good:

```php
use Morcen\Passage\Facades\Passage;

Passage::any('api/transactions/{path?}', TransactionsPassageController::class);
Passage::any('api/payments/{path?}', PaymentsPassageController::class);
```

Less ideal:

```php
Passage::any('api/{service}/{path?}', GenericPassageController::class);
```

The explicit approach is usually better because it:

- avoids accidental open-proxy behavior
- makes service boundaries clear
- lets each service have its own auth, headers, timeout, and validation rules
- keeps `route:list` readable

### Example handler responsibilities

For `TransactionsPassageController`:

- set `base_uri` to the transactions service
- attach an internal API token
- validate allowed methods or required headers
- remove internal-only upstream fields from responses if needed

For `PaymentsPassageController`:

- set `base_uri` to the payments service
- add payment service credentials
- enforce route middleware like auth or throttling
- reshape upstream validation errors into your app's preferred format

## Scenario 2: Build a backend-for-frontend for your SPA or mobile app

### Situation

Your frontend needs data from different upstream APIs, but you do not want the client to know about all of them.

You want the frontend to talk only to your Laravel app, and you want your app to manage:

- auth headers
- request payload formats
- stable route naming
- response cleanup

### Why Passage helps

Passage lets you expose app-owned routes while still forwarding requests to the right upstream API. This reduces client-side complexity and keeps provider-specific details out of the frontend.

### Best way to implement it

Create one Passage handler per upstream domain or capability.

Examples:

```php
Passage::get('api/profile/{path?}', ProfilePassageController::class);
Passage::any('api/billing/{path?}', BillingPassageController::class);
Passage::any('api/notifications/{path?}', NotificationsPassageController::class);
```

Then use Laravel middleware groups to enforce app-level rules:

```php
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Passage::get('profile/{path?}', ProfilePassageController::class);
    Passage::any('billing/{path?}', BillingPassageController::class);
});
```

### Recommended pattern

- Keep the public route names client-friendly
- Let each handler map those routes to the upstream API shape
- Avoid leaking vendor-specific endpoint names directly into your public API unless that is intentional

## Scenario 3: Put consistent auth and policy checks in front of internal services

### Situation

You have multiple internal APIs, and you want every inbound request to pass through Laravel first so you can apply:

- authentication
- authorization
- tenant checks
- rate limiting
- audit logging

### Why Passage helps

Passage works naturally with Laravel route middleware, so you can treat proxy routes like first-class application routes instead of building a separate proxy layer from scratch.

### Best way to implement it

Put Passage routes inside middleware groups:

```php
Route::prefix('api')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->group(function () {
        Passage::any('transactions/{path?}', TransactionsPassageController::class);
        Passage::any('payments/{path?}', PaymentsPassageController::class);
    });
```

Then use each handler to attach service-to-service credentials before forwarding upstream.

### Important note

Passage helps you route and transform requests, but your actual trust model still depends on your app and infrastructure. If you need stronger service-to-service guarantees, combine Passage with controls like:

- internal API tokens
- signed requests
- network allowlists
- mTLS
- upstream response validation

## Scenario 4: Hide provider changes behind stable application routes

### Situation

Your app depends on a third-party API today, but you want freedom to replace it later without forcing your consumers to change their integrations.

### Why Passage helps

Passage lets your app publish routes that belong to your product, not to the vendor. Your handlers can adapt requests and responses to the current provider while preserving the contract your clients already use.

### Best way to implement it

Create routes that reflect your product language, not the upstream vendor's language.

Good:

```php
Passage::get('api/customer-orders/{path?}', OrdersPassageController::class);
```

Instead of exposing the provider's exact route naming, let the handler translate your public route into the provider's expected request.

### Recommended pattern

- keep public routes stable
- keep provider-specific tokens in the handler or config
- use `getRequest()` to translate request details
- use `getResponse()` to normalize provider-specific response shapes

## Scenario 5: Add lightweight request and response shaping without building a custom proxy from scratch

### Situation

You mostly want to pass requests through, but you also need small customizations like:

- adding headers
- renaming fields
- dropping sensitive response keys
- validating inbound request details before forwarding

### Why Passage helps

Passage gives you a focused extension point for each route through the handler methods:

- `getOptions()`
- `getRequest()`
- `getResponse()`

That is often enough for lightweight gateway behavior without introducing a much bigger abstraction.

### Best way to implement it

Keep handlers small and single-purpose.

Good handler responsibilities:

- set one upstream service
- inject one service's auth rules
- perform small request or response adjustments

Avoid turning one handler into a full orchestration layer for many unrelated services.

## Scenario 6: Gradually introduce a gateway layer into an existing Laravel app

### Situation

You already have a Laravel app, and you want to place some external API calls behind app-owned endpoints without rewriting your whole architecture.

### Why Passage helps

Passage fits into normal Laravel routing, so you can introduce it service by service instead of doing a large migration.

### Best way to implement it

Start with one high-value service:

- payment provider
- transaction service
- shipping provider
- customer account API

Define a small set of explicit routes, add a handler, and place those routes behind your existing middleware.

This keeps adoption incremental and low-risk.

## Implementation tips

### Prefer explicit routes over one dynamic catch-all

This is usually the safest and clearest pattern:

```php
Passage::any('api/transactions/{path?}', TransactionsPassageController::class);
Passage::any('api/payments/{path?}', PaymentsPassageController::class);
Passage::any('api/customers/{path?}', CustomersPassageController::class);
```

It gives each service its own:

- base URI
- auth rules
- headers
- middleware
- timeout settings

### Use dynamic service routing only when you can tightly control it

A route like `api/{service}/{path?}` is possible in principle, but it should only be used if you carefully:

- whitelist allowed service names
- map each service to a known upstream URL
- reject unknown services
- prevent arbitrary destination forwarding
- isolate per-service credentials and policies

If you need that style, treat it as custom application logic built on top of Passage, not the default recommendation.

### Keep handlers close to business boundaries

A good rule of thumb is one handler per upstream capability or service boundary. That keeps the code easier to reason about and avoids a single generic proxy becoming too powerful or too hard to secure.

## Summary

Passage is most useful when you want Laravel to become the controlled entrypoint in front of upstream APIs.

It shines when you want to:

- publish your own stable routes
- secure and shape requests before they leave your app
- normalize upstream responses
- reuse Laravel routing and middleware instead of building a proxy layer from scratch

If that sounds like your architecture, Passage can give you a lightweight and practical starting point.
