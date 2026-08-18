<?php

use Illuminate\Support\Facades\File;

describe('PassageCommand', function () {
    beforeEach(function () {
        File::copyDirectory(__DIR__.'/../../resources/stubs', base_path('stubs'));
    });

    afterEach(function () {
        File::deleteDirectory(base_path('stubs'));
        File::deleteDirectory(app_path('Http/Controllers/Passages'));
    });

    it('generates a handler from the default stub', function () {
        $this->artisan('passage:controller DefaultHandler')
            ->expectsOutputToContain('Passage handler created at app/Http/Controllers/Passages/DefaultHandler.php')
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/DefaultHandler.php'));

        expect($contents)
            ->toContain('class DefaultHandler extends PassageHandler')
            ->not->toContain('withRetry');
    });

    it('generates a retry-enabled handler with --with-retry', function () {
        $this->artisan('passage:controller RetryHandler --with-retry')
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/RetryHandler.php'));

        expect($contents)
            ->toContain('$this->withRetry(3, 200)')
            ->toContain('array_merge');
    });

    it('generates a cached handler with --with-cache', function () {
        $this->artisan('passage:controller CachedHandler --with-cache')
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/CachedHandler.php'));

        expect($contents)->toContain('passage_cache_ttl');
    });

    it('warns and prefers cache when --with-cache and --with-retry are combined', function () {
        $this->artisan('passage:controller CacheRetryHandler --with-cache --with-retry')
            ->expectsOutputToContain('--with-retry is ignored when --with-cache is used')
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/CacheRetryHandler.php'));

        expect($contents)
            ->toContain('passage_cache_ttl')
            ->not->toContain('withRetry');
    });

    it('warns and prefers auth when --with-auth and --with-retry are combined', function () {
        $this->artisan('passage:controller AuthRetryHandler --with-auth=bearer --with-retry')
            ->expectsOutputToContain('--with-retry is ignored when --with-auth is used')
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/AuthRetryHandler.php'));

        expect($contents)->not->toContain('$this->withRetry(3, 200)');
    });

    it('warns when --with-auth and --with-cache are combined', function () {
        $this->artisan('passage:controller AuthCacheHandler --with-auth=bearer --with-cache')
            ->expectsOutputToContain('--with-cache is ignored when --with-auth is used')
            ->assertExitCode(0);
    });

    it('falls back to the retry stub when --with-auth is unknown and --with-retry is set', function () {
        $this->artisan('passage:controller UnknownAuthRetryHandler --with-auth=oauth --with-retry')
            ->expectsOutputToContain("Unknown --with-auth value 'oauth'")
            ->assertExitCode(0);

        $contents = File::get(app_path('Http/Controllers/Passages/UnknownAuthRetryHandler.php'));

        expect($contents)->toContain('$this->withRetry(3, 200)');
    });

    it('fails when the handler already exists', function () {
        $this->artisan('passage:controller DuplicateHandler --with-retry')
            ->assertExitCode(0);

        $this->artisan('passage:controller DuplicateHandler --with-retry')
            ->expectsOutputToContain('already exists')
            ->assertExitCode(1);
    });

    it('rejects a handler name containing path traversal segments', function () {
        $this->artisan('passage:controller ../../../../tmp/evil')
            ->expectsOutputToContain('Invalid handler name')
            ->assertExitCode(1);

        expect(File::exists(app_path('Http/Controllers/Passages/evil.php')))->toBeFalse();
    });

    it('rejects a handler name containing unexpected characters', function () {
        $this->artisan('passage:controller "Evil; rm -rf /"')
            ->expectsOutputToContain('Invalid handler name')
            ->assertExitCode(1);
    });
});
