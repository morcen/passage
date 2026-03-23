<?php

namespace Morcen\Passage;

use Morcen\Passage\Commands\PassageCommand;
use Morcen\Passage\Commands\PassageListCommand;
use Morcen\Passage\Services\PassageService;
use Morcen\Passage\Services\PassageServiceInterface;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PassageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('passage')
            ->hasCommands([PassageCommand::class, PassageListCommand::class])
            ->hasConfigFile()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('morcen/passage');
            });

        $this->publishes([
            __DIR__.'/../resources/stubs/' => base_path('stubs'),
        ], 'passage-stubs');
    }

    public function packageBooted(): void
    {
        $passage = config('passage');

        if (isset($passage['enabled']) && $passage['enabled']) {
            $this->app->bind(PassageServiceInterface::class, PassageService::class);
        }
    }
}
