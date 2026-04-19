<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay;

use Core45\LaravelTubaPay\Console\Commands\CheckConnectionCommand;
use Core45\LaravelTubaPay\Console\Commands\PruneCheckoutSelectionsCommand;
use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Http\LaravelTokenStorage;
use Core45\LaravelTubaPay\Listeners\DefaultHandlePaymentReceived;
use Core45\LaravelTubaPay\Listeners\DefaultHandleRecurringOrderRequested;
use Core45\LaravelTubaPay\Listeners\DefaultHandleTransactionAccepted;
use Core45\LaravelTubaPay\Listeners\DefaultHandleTransactionRejected;
use Core45\LaravelTubaPay\Services\EloquentCheckoutSelectionStore;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutService;
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;
use Core45\LaravelTubaPay\Services\TubaPayWebhookEventStore;
use Core45\LaravelTubaPay\Services\TubaPayWebhookPersistence;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class TubaPayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tubapay.php',
            'tubapay'
        );

        $this->app->singleton(LaravelTokenStorage::class, function ($app) {
            /** @var CacheRepository $cache */
            $cache = $app['cache']->store(config('tubapay.cache.store'));

            return new LaravelTokenStorage(
                $cache,
                (string) config('tubapay.cache.prefix', 'tubapay_'),
                (int) config('tubapay.cache.expiration_buffer', 60)
            );
        });

        $this->app->singleton(TubaPay::class, function ($app) {
            $environment = $this->resolveEnvironment();

            /** @var LaravelTokenStorage $tokenStorage */
            $tokenStorage = $app->make(LaravelTokenStorage::class);

            $logger = $this->resolveLogger($app);

            return TubaPay::create(
                clientId: (string) config('tubapay.client_id', ''),
                clientSecret: (string) config('tubapay.client_secret', ''),
                webhookSecret: (string) config('tubapay.webhook_secret', ''),
                environment: $environment,
                tokenStorage: $tokenStorage,
                logger: $logger,
            );
        });

        $this->app->bind(CheckoutSelectionStore::class, EloquentCheckoutSelectionStore::class);
        $this->app->singleton(TubaPayCheckoutOptions::class);
        $this->app->singleton(TubaPayCheckoutService::class);
        $this->app->singleton(TubaPayStatusMapper::class);
        $this->app->singleton(TubaPayWebhookEventStore::class);
        $this->app->singleton(TubaPayWebhookPersistence::class);
    }

    /**
     * Resolve the logger based on configuration.
     *
     * Logging is enabled when:
     * - TUBAPAY_LOG_REQUESTS=true, or
     * - APP_DEBUG=true (automatic debugging in development)
     *
     * @param  Application  $app
     */
    private function resolveLogger($app): ?LoggerInterface
    {
        $logRequests = (bool) config('tubapay.logging.log_requests', false);
        $appDebug = (bool) config('app.debug', false);

        if (! $logRequests && ! $appDebug) {
            return null;
        }

        /** @var string|null $channel */
        $channel = config('tubapay.logging.channel');

        /** @var LogManager $logManager */
        $logManager = $app->make('log');

        return $logManager->channel($channel);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/tubapay.php' => config_path('tubapay.php'),
        ], 'tubapay-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'tubapay-migrations');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tubapay');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/tubapay'),
        ], 'tubapay-lang');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tubapay');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tubapay'),
        ], 'tubapay-views');

        if ($this->shouldRegisterRoutes()) {
            $this->registerRoutes();
        }

        if ($this->shouldRegisterUiRoutes()) {
            $this->registerUiRoutes();
        }

        if ($this->shouldAutoRegisterListeners()) {
            $this->registerListeners();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckConnectionCommand::class,
                PruneCheckoutSelectionsCommand::class,
            ]);
        }
    }

    /**
     * Resolve the TubaPay environment from configuration.
     */
    private function resolveEnvironment(): Environment
    {
        $environment = config('tubapay.environment', 'test');

        return match ($environment) {
            'production', 'prod', 'live' => Environment::Production,
            default => Environment::Test,
        };
    }

    /**
     * Determine if routes should be registered.
     */
    private function shouldRegisterRoutes(): bool
    {
        return (bool) config('tubapay.webhook.register_route', true);
    }

    private function shouldRegisterUiRoutes(): bool
    {
        return (bool) config('tubapay.ui.register_routes', false);
    }

    private function shouldAutoRegisterListeners(): bool
    {
        return (bool) config('tubapay.listeners.auto_register', false);
    }

    /**
     * Register the webhook routes.
     */
    private function registerRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $router->group([
            'middleware' => config('tubapay.webhook.middleware', ['api']),
        ], function (Router $router): void {
            $router->post(
                (string) config('tubapay.webhook.path', 'webhooks/tubapay'),
                [Http\Controllers\WebhookController::class, 'handle']
            )->name('tubapay.webhook');
        });
    }

    private function registerUiRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $router->group([
            'middleware' => config('tubapay.ui.routes_middleware', ['web']),
            'prefix' => 'tubapay',
        ], function (Router $router): void {
            $router->get('installments', [Http\Controllers\UiController::class, 'installments'])
                ->name('tubapay.ui.installments');
            $router->get('content/top-bar', [Http\Controllers\UiController::class, 'topBar'])
                ->name('tubapay.ui.content.top-bar');
            $router->get('content/popup', [Http\Controllers\UiController::class, 'popup'])
                ->name('tubapay.ui.content.popup');
            $router->get('texts', [Http\Controllers\UiController::class, 'texts'])
                ->name('tubapay.ui.texts');
        });
    }

    private function registerListeners(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        $events->listen(TransactionStatusChanged::class, DefaultHandleTransactionAccepted::class);
        $events->listen(TransactionStatusChanged::class, DefaultHandleTransactionRejected::class);
        $events->listen(PaymentReceived::class, DefaultHandlePaymentReceived::class);
        $events->listen(RecurringOrderRequested::class, DefaultHandleRecurringOrderRequested::class);
    }
}
