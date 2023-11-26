<?php

namespace Shawnveltman\LaravelOpenai;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Shawnveltman\LaravelOpenai\Commands\LaravelOpenaiCommand;

class LaravelOpenaiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-openai')
            ->hasConfigFile('openai')
            ->hasViews()
            ->hasMigration('create_cost_logs_table');
//            ->hasCommand(LaravelOpenaiCommand::class);
    }
}
