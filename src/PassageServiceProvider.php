<?php

namespace Morcen\Passage;

use Morcen\Passage\Commands\PassageCommand;
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
}
