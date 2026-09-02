# Changelog

All notable changes to `passage` will be documented in this file.

## v3.1.2 - 2026-08-29

### What's Changed

* fix: match form-urlencoded Content-Type case-insensitively in PassageService by @morcen in https://github.com/morcen/passage/pull/206
* fix: passage:health now fails routes with a missing base_uri by @morcen in https://github.com/morcen/passage/pull/207
* fix: stream uploaded files instead of buffering them into memory by @morcen in https://github.com/morcen/passage/pull/208
* fix: validate handler name in passage:controller before use by @morcen in https://github.com/morcen/passage/pull/209
* fix: share a single hop-by-hop header fallback list between request and response paths by @morcen in https://github.com/morcen/passage/pull/210
* fix: resolve passage:list handler targets through the container by @morcen in https://github.com/morcen/passage/pull/211
* fix: match passage:list routes by fully-qualified controller name by @morcen in https://github.com/morcen/passage/pull/212
* fix: passage:list returns success exit code when Passage is disabled by @morcen in https://github.com/morcen/passage/pull/213
* fix(commands): return failure exit code when passage:controller generation fails by @morcen in https://github.com/morcen/passage/pull/214
* fix: make PassageEventSubscriber fall back to the default log channel by @morcen in https://github.com/morcen/passage/pull/215
* fix: declare transitive composer dependencies used directly by src/ by @morcen in https://github.com/morcen/passage/pull/216
* fix: require stable composer dependencies instead of dev stability by @morcen in https://github.com/morcen/passage/pull/217
* fix: add X-Forwarded-* headers to upstream requests by @morcen in https://github.com/morcen/passage/pull/218
* test: cover passage:health probe() failure for unreachable hosts by @morcen in https://github.com/morcen/passage/pull/219

**Full Changelog**: https://github.com/morcen/passage/compare/v3.1.1...v3.1.2

## v3.1.1 - 2026-08-22

### What's Changed

* Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/morcen/passage/pull/48
* feat: add hmac controller stub by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/58
* test: cover mixed-case allowed client headers by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/59
* test: cover missing base_uri response by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/60
* docs: document $when callback for conditional retry by @Deepak8858 in https://github.com/morcen/passage/pull/61
* docs: fix @param type hint for retry `$when` callback signature by @morcen in https://github.com/morcen/passage/pull/62
* fix: return structured JSON for invalid base URIs by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/71
* docs: add usage example to PassageControllerInterface by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/73
* fix: make passage:list resilient to broken handlers by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/74
* test: cover PassageEventSubscriber log output by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/75
* docs: add @param and @return tags to PassageCacheManager issue#67 by @MozamilS in https://github.com/morcen/passage/pull/70
* build(deps): bump dependabot/fetch-metadata from 3.0.0 to 3.1.0 by @dependabot[bot] in https://github.com/morcen/passage/pull/78
* fix: resolve security audit advisories by allowing Laravel 12 by @morcen in https://github.com/morcen/passage/pull/81
* feat: scaffold retry-enabled handlers for passage:controller --with-retry by @morcen in https://github.com/morcen/passage/pull/80
* build(deps): bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/morcen/passage/pull/79

### New Contributors

* @abhijeetnardele24-hash made their first contribution in https://github.com/morcen/passage/pull/58
* @Deepak8858 made their first contribution in https://github.com/morcen/passage/pull/61
* @MozamilS made their first contribution in https://github.com/morcen/passage/pull/70

**Full Changelog**: https://github.com/morcen/passage/compare/v3.0.0...v3.1.1

## v3.1.0 - 2026-08-09

### What's Changed

* Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/morcen/passage/pull/48
* feat: add hmac controller stub by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/58
* test: cover mixed-case allowed client headers by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/59
* test: cover missing base_uri response by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/60
* docs: document $when callback for conditional retry by @Deepak8858 in https://github.com/morcen/passage/pull/61
* docs: fix @param type hint for retry `$when` callback signature by @morcen in https://github.com/morcen/passage/pull/62
* fix: return structured JSON for invalid base URIs by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/71
* docs: add usage example to PassageControllerInterface by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/73
* fix: make passage:list resilient to broken handlers by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/74
* test: cover PassageEventSubscriber log output by @abhijeetnardele24-hash in https://github.com/morcen/passage/pull/75
* docs: add @param and @return tags to PassageCacheManager issue#67 by @MozamilS in https://github.com/morcen/passage/pull/70
* build(deps): bump dependabot/fetch-metadata from 3.0.0 to 3.1.0 by @dependabot[bot] in https://github.com/morcen/passage/pull/78
* fix: resolve security audit advisories by allowing Laravel 12 by @morcen in https://github.com/morcen/passage/pull/81
* feat: scaffold retry-enabled handlers for passage:controller --with-retry by @morcen in https://github.com/morcen/passage/pull/80
* build(deps): bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/morcen/passage/pull/79

### New Contributors

* @abhijeetnardele24-hash made their first contribution in https://github.com/morcen/passage/pull/58
* @Deepak8858 made their first contribution in https://github.com/morcen/passage/pull/61
* @MozamilS made their first contribution in https://github.com/morcen/passage/pull/70

**Full Changelog**: https://github.com/morcen/passage/compare/v3.0.0...v3.1.0

## v3.0.0 - 2026-04-03

### What's Changed

* Bump stefanzweifel/git-auto-commit-action from 6 to 7 by @dependabot[bot] in https://github.com/morcen/passage/pull/21
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/morcen/passage/pull/24
* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/morcen/passage/pull/28
* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/morcen/passage/pull/36
* Add tests for Passages with controllers by @morcen in https://github.com/morcen/passage/pull/40
* feat!: introduce route-based Passage API (v3.0.0) by @morcen in https://github.com/morcen/passage/pull/41
* refactor: improve proxy fidelity with content-aware forwarding and response building by @morcen in https://github.com/morcen/passage/pull/49
* feat: add security layer with auth traits, allowed hosts guard, and header stripping by @morcen in https://github.com/morcen/passage/pull/50
* feat: add v3.0 features - route facade, response caching, events, retries, and error handling by @morcen in https://github.com/morcen/passage/pull/51
* feat: add security, auth, resilience, caching, streaming, and observability features by @morcen in https://github.com/morcen/passage/pull/52

**Full Changelog**: https://github.com/morcen/passage/compare/v2.1.0...v3.0.0

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
