<?php

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
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

    it('fails and relays output when the underlying make:controller call fails', function () {
        $realKernel = app(ConsoleKernelContract::class);

        // Orchestra\Testbench\Console\Kernel is final, so it can't be mocked directly.
        // Decorate it instead: intercept the nested make:controller call and delegate
        // everything else (including the outer passage:controller invocation) as-is.
        $fakeKernel = new class($realKernel) implements ConsoleKernelContract
        {
            public function __construct(private ConsoleKernelContract $kernel) {}

            public function call($command, array $parameters = [], $outputBuffer = null)
            {
                if (str_starts_with($command, 'make:controller Passages/FailingHandler')) {
                    return Command::FAILURE;
                }

                return $this->kernel->call($command, $parameters, $outputBuffer);
            }

            public function output()
            {
                return 'Controller already exists.';
            }

            public function bootstrap()
            {
                return $this->kernel->bootstrap();
            }

            public function handle($input, $output = null)
            {
                return $this->kernel->handle($input, $output);
            }

            public function queue($command, array $parameters = [])
            {
                return $this->kernel->queue($command, $parameters);
            }

            public function all()
            {
                return $this->kernel->all();
            }

            public function terminate($input, $status)
            {
                return $this->kernel->terminate($input, $status);
            }
        };

        app()->instance(ConsoleKernelContract::class, $fakeKernel);

        $this->artisan('passage:controller FailingHandler')
            ->expectsOutputToContain('Failed to create passage handler FailingHandler.')
            ->expectsOutputToContain('Controller already exists.')
            ->assertExitCode(1);

        expect(File::exists(app_path('Http/Controllers/Passages/FailingHandler.php')))->toBeFalse();
    });
});
