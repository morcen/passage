<?php

namespace Morcen\Passage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\Services\PassageService;
use Morcen\Passage\Services\PassageServiceInterface;
use PharIo\Manifest\InvalidUrlException;
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
        $package
            ->name('passage')
            ->hasConfigFile();
    }

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
            foreach ($services as $service => $serviceOptions) {
                $macroOptions = array_merge($globalOptions, $serviceOptions);

                Http::macro(
                    $service,
                    fn() => Http::withOptions($macroOptions)
                );
            }
        }
    }
}
