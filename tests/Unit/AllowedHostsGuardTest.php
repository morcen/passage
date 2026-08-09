<?php

use Morcen\Passage\Exceptions\DisallowedProxyTargetException;
use Morcen\Passage\Guards\AllowedHostsGuard;

beforeEach(function () {
    $this->guard = new AllowedHostsGuard;
});

describe('AllowedHostsGuard', function () {
    describe('when enforce_allowed_hosts is false (default)', function () {
        it('permits any host without checking the allowlist', function () {
            config([
                'passage.security.enforce_allowed_hosts' => false,
                'passage.security.allowed_hosts' => [],
            ]);

            // Should not throw for any host
            expect(fn () => $this->guard->check('https://api.example.com/'))->not->toThrow(DisallowedProxyTargetException::class);
            expect(fn () => $this->guard->check('https://evil.example.com/'))->not->toThrow(DisallowedProxyTargetException::class);
        });
    });

    describe('when enforce_allowed_hosts is true', function () {
        beforeEach(function () {
            config([
                'passage.security.enforce_allowed_hosts' => true,
                'passage.security.allowed_hosts' => ['api.github.com', 'api.stripe.com'],
            ]);
        });

        it('permits hosts in the allowlist', function () {
            expect(fn () => $this->guard->check('https://api.github.com/v3/'))->not->toThrow(DisallowedProxyTargetException::class);
            expect(fn () => $this->guard->check('https://api.stripe.com/'))->not->toThrow(DisallowedProxyTargetException::class);
        });

        it('throws for hosts not in the allowlist', function () {
            expect(fn () => $this->guard->check('https://api.evil.com/'))
                ->toThrow(DisallowedProxyTargetException::class);
        });

        it('throws for a subdomain not explicitly listed', function () {
            expect(fn () => $this->guard->check('https://v3.api.github.com/'))
                ->toThrow(DisallowedProxyTargetException::class);
        });

        it('throws when base_uri has no parseable host', function () {
            expect(fn () => $this->guard->check('not-a-url'))
                ->toThrow(DisallowedProxyTargetException::class);
        });

        it('permits an allowlisted host regardless of casing', function () {
            expect(fn () => $this->guard->check('https://API.GitHub.com/v3/'))->not->toThrow(DisallowedProxyTargetException::class);
            expect(fn () => $this->guard->check('https://Api.Stripe.Com/'))->not->toThrow(DisallowedProxyTargetException::class);
        });
    });

    describe('checkHost()', function () {
        it('permits any host when enforce_allowed_hosts is false', function () {
            config([
                'passage.security.enforce_allowed_hosts' => false,
                'passage.security.allowed_hosts' => [],
            ]);

            expect(fn () => $this->guard->checkHost('evil.example.com'))->not->toThrow(DisallowedProxyTargetException::class);
        });

        it('permits an allowlisted host when enforce_allowed_hosts is true', function () {
            config([
                'passage.security.enforce_allowed_hosts' => true,
                'passage.security.allowed_hosts' => ['api.github.com'],
            ]);

            expect(fn () => $this->guard->checkHost('api.github.com'))->not->toThrow(DisallowedProxyTargetException::class);
        });

        it('throws for a host outside the allowlist, e.g. a redirect target', function () {
            config([
                'passage.security.enforce_allowed_hosts' => true,
                'passage.security.allowed_hosts' => ['api.github.com'],
            ]);

            expect(fn () => $this->guard->checkHost('evil.example.com'))
                ->toThrow(DisallowedProxyTargetException::class);
        });

        it('permits an allowlisted redirect target regardless of casing', function () {
            config([
                'passage.security.enforce_allowed_hosts' => true,
                'passage.security.allowed_hosts' => ['api.github.com'],
            ]);

            expect(fn () => $this->guard->checkHost('API.GitHub.com'))
                ->not->toThrow(DisallowedProxyTargetException::class);
        });
    });
});
