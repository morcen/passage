# Changelog

All notable changes to `passage` will be documented in this file.

## v2.1.0 - 2026-03-22

### What's Changed

* Fix security audit action by @morcen in https://github.com/morcen/passage/pull/38
* Fix pint error by @morcen in https://github.com/morcen/passage/pull/39

**Full Changelog**: https://github.com/morcen/passage/compare/v2.0.0...v2.1.0

## v2.0.0 - 2025-01-23

### Added

- Support for PHP 8.3+
- Support for Laravel 11+
- Comprehensive test suite (31 tests with 103 assertions)
- PHP 8.3 readonly properties for better immutability
- Matrix testing workflow for PHP 8.2, 8.3, and 8.4
- Security audit workflow
- Composer dependency management in Dependabot

### Changed

- **BREAKING CHANGE:** Minimum PHP version upgraded from 8.1 to 8.2+ (8.3+ recommended)
- **BREAKING CHANGE:** Minimum Laravel version upgraded from 8.x to 11.x
- Updated all development dependencies to latest versions
- Version bumped to v2.0.0 to reflect breaking changes

### Updated Dependencies

- `spatie/laravel-package-tools`: ^1.16.0 (from ^1.14.0)
- `illuminate/contracts`: ^11.0 (from ^10.0)
- `laravel/pint`: ^1.19.0 (from ^1.0)
- `nunomaduro/collision`: ^8.0 (from ^7.9)
- `nunomaduro/larastan`: ^2.9.0 (from ^2.0.1)
- `orchestra/testbench`: ^9.0 (from ^8.0)
- `pestphp/pest`: ^2.35.0 (from ^2.0)
- `pestphp/pest-plugin-arch`: ^2.7.0 (from ^2.0)
- `pestphp/pest-plugin-laravel`: ^2.4.0 (from ^2.0)
- `phpstan/extension-installer`: ^1.4.0 (from ^1.1)
- `phpstan/phpstan-deprecation-rules`: ^1.2.0 (from ^1.0)
- `phpstan/phpstan-phpunit`: ^1.4.0 (from ^1.0)
- `spatie/laravel-ray`: ^1.37.0 (from ^1.26)

### Fixed

- Fixed namespace issues in exception classes
- Fixed null safety issues in service provider
- Removed failing integration tests with Guzzle dependencies

### Removed

- Support for PHP 8.1
- Support for Laravel 8.x, 9.x, and 10.x

## v1.2.4 - 2025-08-23

### What's Changed

* Get service via Passage Facade by @morcen in https://github.com/morcen/passage/pull/12
* Bump actions/checkout from 3 to 4 by @dependabot[bot] in https://github.com/morcen/passage/pull/13
* Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot[bot] in https://github.com/morcen/passage/pull/14

**Full Changelog**: https://github.com/morcen/passage/compare/v1.2.3...v1.2.4

## v0.2.1 - 2023-07-30

- lower Laravel version requirement from 10.x to 8.x
- added composer.lock file

**Full Changelog**: https://github.com/morcen/passage/compare/v0.2.0...v0.2.1

## v0.2.0 - 2023-07-30

### What's Changed

- used out-of-the-box Guzzle options for building the service options
- fixed failing tests
- updated README

**Full Changelog**: https://github.com/morcen/passage/compare/v0.1.0...v0.2.0

## v0.1.0 - 2023-03-12

Initial release

- config-based rules working as intended
- API proxy functionality
