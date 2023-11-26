<?php

namespace Shawnveltman\LaravelOpenai\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Shawnveltman\LaravelOpenai\LaravelOpenaiServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Shawnveltman\\LaravelOpenai\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelOpenaiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');


        $migration = include __DIR__.'/../database/migrations/create_cost_logs_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_users_table.php.stub';
        $migration->up();
    }
}
