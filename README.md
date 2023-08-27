# Passage: Lightweight API proxy gateway for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)

## Introduction

This is a powerful API Gateway package for Laravel that allows you to efficiently manage and route incoming API requests to various microservices. It simplifies the process of handling API requests and responses, enabling seamless communication between clients and microservices.

## Features

- Request routing to multiple microservices
- Request payload validation and transformation
- Response payload transformation
- Authentication and authorization handling (coming soon)
- Rate limiting and throttling (coming soon)
- Caching and response caching (coming soon)
- Error handling and logging (coming soon)

## Requirements

- PHP version 8.1 or higher
- Laravel version 8.x or higher

## Installation

You can install the package via composer:

```bash
composer require morcen/passage
```

Then install the package using the following command:
```bash
php artisan passage:install
```

This will publish the package's config file at `config/passage.php`.

If you wish to create a controller for handling requests, publish the controller stub using the following command:
```bash
php artisan vendor:publish --tag=passage-stubs
```

and then generate Passage controllers by running:
```bash
php artisan passage:controller {name}
```
where `{name}` is the name of the controller you want to generate.


## Usage

#### Enabling `Passage`
To start using this package, add this line in your `routes/web.php` to enable Passage:
```php
Route::passage();
```

And make sure that in your `.env`, either `PASSAGE_ENABLED` is not set or it is set as `true`:
```env
PASSAGE_ENABLED=true
```

#### Setting gateway routes
##### Passage as a Proxy
In `config/passage.php`, define the services you want to be forwarded.

Example:
```php
// config/passage.php
return [
    'services' => [
        // Forwards `GET http://{your-host}/github/users/morcen` to `GET https://api.github.com/users/morcen`:
        'github' => [ // <-- This is the name of the service
            'base_uri' => 'http://users-service/api/v1/', // <-- This is where the request will be forwarded to
            // other options at https://docs.guzzlephp.org/en/stable/request-options.html
        ],
    ]
]
```
> **Note**
> - Make sure that the `base_uri` ends with a trailing slash `/`, otherwise the request might not be forwarded properly.
> - All headers, query parameters, as well as the type of request (`GET`, `POST`, etc.) will be forwarded to the service.

##### Transforming/validating requests and response through a Passage controller
If you have a service called `github` and you want to handle the incoming and outgoing payloads, you can create a controller for it by running:
```bash
php artisan passage:controller GithubPassageController
```
This will create a controller at `app/Http/Controllers/Passage/GithubPassageController.php`.

In your controller, you can define the following methods:
```php
// app/Http/Controllers/Passage/GithubPassageController.php
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\PassageControllerInterface;

class GithubPassageController extends Controller implements PassageControllerInterface
{
    /**
     * Transform and/or validate the request before it is sent to the service.
     *
     * @param  Request  $request
     * @return Request
     */
    public function getRequest(Request $request): Request
    {
        // Transform the request here
        return $request;
    }

    /**
     * Transform or validate the response before it is sent back to the client.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function getResponse(Request $request, Response $response): Response
    {
        // Transform the response here
        return $response;
    }

    /**
     * Set the route options when the service is instantiated.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [
             'base_uri' => 'https://api.github.com/',
        ];
    }
}
```

In your `config/passage.php`, add the controller to the `services` array:
```php
// config/passage.php
return [
    'services' => [
        'github' => \App\Http\Controllers\Passage\GithubPassageController::class, // <-- Add this line,
    ]
]
```

#### Using the `Passage` facade
If you wish not to use automatic routing of Passage and instead use the Passage services manuall in your controllers, you can use the `Passage` facade.
```php
// config/passage.php
return [
    'services' => [
        'github' => 'https://api.github.com/',
    ]
]
```

and in your controller:
```php
// app/Http/Controllers/UserController.php

use Morcen\Passage\Facades\Passage

class UserController extends Controller
{
    public function index()
    {
        $response = Passage::getService('github')->get('users/morcen');
        return $response->json();
    }
}
```

### Disabling `Passage`
To disable `Passage` on a server/application level, set `PASSAGE_ENABLED` to `false` in your `.env` file:
```env
PASSAGE_ENABLED=false
```

Alternatively, comment this line in your `routes/web.php`:
```php
Route::passage();
```

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
