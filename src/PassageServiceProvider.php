<?php

namespace Morcen\Passage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Commands\PassageCommand;
use Morcen\Passage\Exceptions\InvalidBaseUriException;
use Morcen\Passage\Exceptions\InvalidPassageHandlerProvided;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\Services\PassageService;
use Morcen\Passage\Services\PassageServiceInterface;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PassageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $this->publishes([
            __DIR__.'/stubs/' => base_path('stubs'),
        ], 'passage-stubs');

        $package
            ->name('passage')
            ->hasCommand(PassageCommand::class)
            ->hasConfigFile()
            ->hasInstallCommand(function(InstallCommand $command) {
                $command->callSilently('vendor:publish', ['--tag' => 'passage-stubs']);
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('morcen/passage');
            });;
    }

    /**
     * @throws InvalidPassageHandlerProvided|InvalidBaseUriException
     */
    public function packageBooted(): void
    {
        $passage = config('passage');

        if (isset($passage['enabled']) && $passage['enabled']) {
            $this->app->bind(PassageServiceInterface::class, PassageService::class);

            Route::macro('passage', function () {
                Route::any('{any?}', [PassageController::class, 'index'])->where('any', '.*');
            });

            $services = $passage['services'];
            $globalOptions = $passage['options'];
            foreach ($services as $service => $handler) {
                $this->handlerCheckpoint($handler, $service);
                $options = $this->extractOptions($handler);

                $macroOptions = array_merge($globalOptions, $options);

                Http::macro(
                    $service,
                    fn () => Http::withOptions($macroOptions)
                );
            }
        }
    }

    /**
     * @throws InvalidPassageHandlerProvided|InvalidBaseUriException
     */
    private function handlerCheckpoint(array|string $handler, string $service): void
    {
        if (is_array($handler)) {
            if (! isset($handler['base_uri'])) {
                throw new InvalidBaseUriException('Base URI is required for service '.$service.'.');
            }
        } elseif (! class_exists($handler) || ! is_subclass_of($handler, PassageControllerInterface::class)) {
            throw new InvalidPassageHandlerProvided('Invalid service handler provided for service '.$service.'.');
        }

        // validation passed; continue
    }

    /**
     * @param  array|string  $handler
     * @return array
     */
    private function extractOptions(array|string $handler): array
    {
        if (is_array($handler)) {
            return $handler;
        }

        $options = [];
        $handlerClass = new $handler();
        if (method_exists($handlerClass, 'getOptions')) {
            return $handlerClass->getOptions();
        }

        return $options;
    }
}
