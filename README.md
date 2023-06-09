# Passage: Lightweight API gateway for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/morcen/passage/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/morcen/passage/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/morcen/passage.svg?style=flat-square)](https://packagist.org/packages/morcen/passage)

Description TODO

## Installation

You can install the package via composer:

```bash
composer require morcen/passage
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="passage-config"
```


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
TODO

#### Disabling `Passage`
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
