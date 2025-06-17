<?php

namespace AnasTalal\Laravice;

use Spatie\LaravelPackageTools\Package;
use AnasTalal\Laravice\Commands\LaraviceCommand;
use AnasTalal\Laravice\Commands\MakeLaraviceCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use AnasTalal\Laravice\Commands\GenerateAllServicesCommand;

class LaraviceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravice')
            ->hasConfigFile()
            // ->hasViews()
            // ->hasMigration('create_laravice_table')
            ->hasCommands([
                MakeLaraviceCommand::class,
                GenerateAllServicesCommand::class
            ])
            ->hasCommand(LaraviceCommand::class)
            // ->hasInstallCommand(function(InstallCommand $command) {
            //     $command
            //         ->publishConfigFile()
            //        // ->publishAssets()
            //        // ->publishMigrations()
            //        // ->copyAndRegisterServiceProviderInApp()
            //         ->askToStarRepoOnGitHub('anastalal/laravice');
            // })
            ;
    }
}
