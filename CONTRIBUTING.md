# Contributing

Contributions are welcome and will be fully credited.

We accept contributions via pull requests on [GitHub](https://github.com/morcen/passage).

## Reporting bugs and requesting features

Please use the [issue templates](https://github.com/morcen/passage/issues/new/choose) when opening a bug report or feature request — they help us get the details we need to help you.

## Pull requests

- **Branch from `main`.** Give your branch a short, descriptive name (e.g. `fix/redirect-guard-cache-key`).
- **Follow existing conventions.** Match the style, naming, and structure already used in the codebase.
- **Add tests.** New features and bug fixes should be covered by [Pest](https://pestphp.com) tests in the `tests/` directory.
- **One pull request per feature.** If you want to contribute multiple unrelated changes, please submit them as separate pull requests.
- **Keep commit messages clear.** Explain *what* changed and *why*.

## Code style

This project follows PSR-12, enforced by [Laravel Pint](https://laravel.com/docs/pint). Before committing, format the files you changed:

```bash
composer format
```

## Running tests

The test suite is run with [Pest](https://pestphp.com):

```bash
composer test
```

Please make sure the full suite passes before opening a pull request.

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x

**Happy coding**!
