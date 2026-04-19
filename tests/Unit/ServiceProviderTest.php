<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\LaravelTubaPay\Http\LaravelTokenStorage;
use Core45\LaravelTubaPay\Services\EloquentCheckoutSelectionStore;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutService;
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;
use Core45\LaravelTubaPay\Services\TubaPayWebhookEventStore;
use Core45\LaravelTubaPay\Services\TubaPayWebhookPersistence;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use PHPUnit\Framework\Attributes\Test;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_tubapay_singleton(): void
    {
        $instance1 = $this->app->make(TubaPay::class);
        $instance2 = $this->app->make(TubaPay::class);

        $this->assertInstanceOf(TubaPay::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function it_registers_token_storage_singleton(): void
    {
        $instance1 = $this->app->make(LaravelTokenStorage::class);
        $instance2 = $this->app->make(LaravelTokenStorage::class);

        $this->assertInstanceOf(LaravelTokenStorage::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function it_registers_checkout_services(): void
    {
        $this->assertInstanceOf(EloquentCheckoutSelectionStore::class, $this->app->make(CheckoutSelectionStore::class));
        $this->assertInstanceOf(TubaPayCheckoutOptions::class, $this->app->make(TubaPayCheckoutOptions::class));
        $this->assertInstanceOf(TubaPayCheckoutService::class, $this->app->make(TubaPayCheckoutService::class));
        $this->assertInstanceOf(TubaPayStatusMapper::class, $this->app->make(TubaPayStatusMapper::class));
        $this->assertInstanceOf(TubaPayWebhookEventStore::class, $this->app->make(TubaPayWebhookEventStore::class));
        $this->assertInstanceOf(TubaPayWebhookPersistence::class, $this->app->make(TubaPayWebhookPersistence::class));
    }

    #[Test]
    public function it_uses_test_environment_by_default(): void
    {
        $tubapay = $this->app->make(TubaPay::class);

        $this->assertSame(Environment::Test, $tubapay->getEnvironment());
    }

    #[Test]
    public function it_uses_production_environment_when_configured(): void
    {
        $this->app['config']->set('tubapay.environment', 'production');

        // Need to recreate the singleton with new config
        $this->app->forgetInstance(TubaPay::class);
        $tubapay = $this->app->make(TubaPay::class);

        $this->assertSame(Environment::Production, $tubapay->getEnvironment());
    }

    #[Test]
    public function it_merges_config(): void
    {
        $this->assertNotNull(config('tubapay.client_id'));
        $this->assertNotNull(config('tubapay.client_secret'));
        $this->assertNotNull(config('tubapay.webhook_secret'));
    }

    #[Test]
    public function it_registers_views(): void
    {
        $this->assertTrue($this->app['view']->exists('tubapay::components.status-badge'));
        $this->assertTrue($this->app['view']->exists('tubapay::components.installment-selector'));
        $this->assertTrue($this->app['view']->exists('tubapay::components.consent-checkboxes'));
        $this->assertTrue($this->app['view']->exists('tubapay::components.top-bar'));
        $this->assertTrue($this->app['view']->exists('tubapay::components.popup'));
    }

    #[Test]
    public function it_registers_translations(): void
    {
        $this->assertNotNull($this->app['translator']->get('tubapay::statuses.draft'));
    }
}
