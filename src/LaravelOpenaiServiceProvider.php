<?php

namespace Shawnveltman\LaravelOpenai;

use Shawnveltman\LaravelOpenai\Commands\LaravelOpenaiCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasConfigFile('ai_providers')
//            ->hasViews()
            ->hasMigration('create_cost_logs_table')
            ->hasMigration('02-add_description_and_job_uuid_to_cost_logs_table')
            ->hasMigration('03_make_user_id_nullable_on_costs_table');
        //            ->hasCommand(LaravelOpenaiCommand::class);
    }
}
