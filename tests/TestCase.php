<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests;

use Core45\LaravelTubaPay\TubaPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TubaPayServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'TubaPay' => \Core45\LaravelTubaPay\Facades\TubaPay::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('tubapay.client_id', 'test-client-id');
        $app['config']->set('tubapay.client_secret', 'test-client-secret');
        $app['config']->set('tubapay.webhook_secret', 'test-webhook-secret');
        $app['config']->set('tubapay.environment', 'test');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
