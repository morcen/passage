<?php

namespace Morcen\Passage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Commands\PassageCommand;
use Morcen\Passage\Http\Controllers\PassageController;
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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_passage_table')
            ->hasCommand(PassageCommand::class);
    }

    public function packageBooted()
    {
        Route::macro('passage', function () {
            Route::any('{any}', [PassageController::class, 'index'])->where('any', '.*');
        });

        $services = config('passage');
        foreach ($services as $service => $config) {
            Http::macro($service, fn () => Http::baseUrl($config['to']));
        }
    }
}
