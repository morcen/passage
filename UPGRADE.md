# Upgrade Guide

## Upgrading from v2.x to v3.0

v3.0 is a breaking release. The config-based service array and the `Route::passage()` macro have been removed in favour of explicit route registration using the `Passage` facade.

### Breaking Changes

#### 1. `config/passage.php` — `services` array removed

The `services` array is no longer read by Passage. Remove it from your config file.

**Before:**
```php
// config/passage.php
'services' => [
    'github' => App\Http\Controllers\Passages\GithubPassageController::class,
],
```

**After:** delete the `services` key entirely. Routes are registered directly (see below).

#### 2. `Route::passage()` macro removed

The `Route::passage()` macro no longer exists.

**Before:**
```php
Route::passage('github');
```

**After:** register routes explicitly using the `Passage` facade:
```php
use Morcen\Passage\Facades\Passage;

Passage::get('github/{path?}', GithubPassageController::class);
Passage::post('github/{path?}', GithubPassageController::class);
// or cover all methods at once:
Passage::any('github/{path?}', GithubPassageController::class);
```

Passage routes return a standard Laravel `Route` instance, so you can chain `.name()`, `.middleware()`, and other route methods as usual.

#### 3. Array-based handler configuration removed

Handlers must be dedicated classes implementing `PassageControllerInterface`. Anonymous arrays or closures are no longer accepted.

#### 4. Handler `getOptions()` must return `base_uri`

Every handler must return at minimum a `base_uri` from `getOptions()`. Passage will throw `InvalidBaseUriException` at request time if it is missing.

```php
public function getOptions(): array
{
    return [
        'base_uri' => 'https://api.example.com/',
    ];
}
```

### Migration Steps

1. Remove the `services` array from `config/passage.php`.
2. Replace every `Route::passage('service-name')` call with `Passage::get/post/any(...)`.
3. Ensure each handler class implements `PassageControllerInterface` and returns a `base_uri` from `getOptions()`.
4. Run `php artisan passage:list` to confirm your routes are registered correctly.
5. Run your test suite: `php artisan test`.

### Generating New Handlers

```bash
php artisan passage:controller YourServiceName
```

The generated stub includes all three required interface methods with inline guidance.

---

## Upgrading from v1.x to v2.0

### Requirements Changes

| | v1.x | v2.0 |
|---|---|---|
| PHP | 8.1+ | 8.2+ |
| Laravel | 8.x+ | 11.x+ |

### Breaking Changes

- Minimum PHP raised to **8.2**.
- Minimum Laravel raised to **11.x**.
- All dev dependencies updated to Laravel 11-compatible versions.

### Migration Steps

1. Upgrade PHP to 8.2+.
2. Upgrade Laravel to 11.x (`composer update laravel/framework`).
3. Update Passage: `composer update morcen/passage`.
4. Run your tests: `php artisan test`.

No API or configuration changes were required between v1 and v2.

---

For issues, please file them on [GitHub](https://github.com/morcen/passage/issues).
