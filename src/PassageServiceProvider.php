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
//            ->hasViews()
//            ->hasMigration('create_passage_table')
//            ->hasCommand(PassageCommand::class)
            ->hasConfigFile();
    }

    public function packageBooted()
    {
        $passage = config('passage');

        if (isset($passage['enabled']) && $passage['enabled']) {
            Route::macro('passage', function () {
                Route::any('{any?}', [PassageController::class, 'index'])->where('any', '.*');
            });

            $services = $passage['services'];
            foreach ($services as $service => $config) {
                Http::macro($service, fn () => Http::baseUrl($config['to']));
            }
        }
    }
}
