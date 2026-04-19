<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | TubaPay Client Credentials
    |--------------------------------------------------------------------------
    |
    | Your TubaPay partner credentials. These are required to authenticate
    | with the TubaPay API. You can obtain these from your TubaPay dashboard.
    |
    */

    'client_id' => env('TUBAPAY_CLIENT_ID'),
    'client_secret' => env('TUBAPAY_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The secret key used to verify webhook signatures. This ensures that
    | incoming webhooks are genuinely from TubaPay.
    |
    */

    'webhook_secret' => env('TUBAPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify webhook signatures. Set to false to disable
    | verification (useful for testing or if TubaPay doesn't enforce it).
    | Note: The official WordPress plugin does not verify signatures.
    |
    */

    'verify_webhook_signatures' => env('TUBAPAY_VERIFY_SIGNATURES', true),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The TubaPay environment to use. Set to 'test' for sandbox testing
    | or 'production' for live transactions.
    |
    | Supported: "test", "production"
    |
    */

    'environment' => env('TUBAPAY_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    |
    | Configuration for the webhook endpoint that receives TubaPay callbacks.
    |
    */

    'webhook' => [
        // The URI path for the webhook endpoint
        'path' => env('TUBAPAY_WEBHOOK_PATH', 'webhooks/tubapay'),

        // Middleware to apply to the webhook route
        'middleware' => ['api'],

        // Whether to automatically register the webhook route
        'register_route' => true,

        // Webhook idempotency protects event dispatch/persistence from retries
        'idempotency' => [
            'enabled' => env('TUBAPAY_WEBHOOK_IDEMPOTENCY', true),
            'lease_minutes' => (int) env('TUBAPAY_WEBHOOK_IDEMPOTENCY_LEASE_MINUTES', 5),
            'max_attempts' => (int) env('TUBAPAY_WEBHOOK_IDEMPOTENCY_MAX_ATTEMPTS', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | The URL where customers are redirected after completing the payment
    | process at TubaPay. This is a client-side redirect, separate from
    | the webhook callback which handles server-to-server notifications.
    |
    */

    'return_url' => env('TUBAPAY_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | Integration Metadata
    |--------------------------------------------------------------------------
    |
    | Metadata sent with transaction creation requests. TubaPay's official
    | WooCommerce plugin sends equivalent fields so partner support can identify
    | integration source/version.
    |
    */

    'integration' => [
        'source' => env('TUBAPAY_INTEGRATION_SOURCE', 'laravel'),
        'app_version' => env('TUBAPAY_APP_VERSION', 'laravel-tubapay'),
        'app_detailed_version' => env('TUBAPAY_APP_DETAILED_VERSION', '0.4.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Defaults used by the checkout selection store and Laravel checkout service.
    |
    */

    'checkout' => [
        'default_installments' => (int) env('TUBAPAY_DEFAULT_INSTALLMENTS', 12),
        'selection_ttl_minutes' => (int) env('TUBAPAY_SELECTION_TTL_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional UI Helpers
    |--------------------------------------------------------------------------
    |
    | These routes and components mirror generic pieces of the official plugin
    | without assuming a cart implementation. Routes are opt-in.
    |
    */

    'ui' => [
        'cache_ttl' => (int) env('TUBAPAY_UI_CACHE_TTL', 3600),
        'register_routes' => env('TUBAPAY_UI_ROUTES', false),
        'routes_middleware' => ['web'],
        'top_bar' => [
            'enabled' => env('TUBAPAY_TOP_BAR_ENABLED', false),
            'sticky' => env('TUBAPAY_TOP_BAR_STICKY', true),
            'font_size' => env('TUBAPAY_TOP_BAR_FONT_SIZE', 16),
            'font_color' => env('TUBAPAY_TOP_BAR_FONT_COLOR', '#ffffff'),
            'background_color' => env('TUBAPAY_TOP_BAR_BACKGROUND_COLOR', '#111827'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Default Listeners
    |--------------------------------------------------------------------------
    |
    | Host apps may opt in after binding TubaPayOrderResolver.
    |
    */

    'listeners' => [
        'auto_register' => env('TUBAPAY_AUTO_LISTENERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Configuration for OAuth token caching. Using Laravel's cache system
    | allows tokens to persist across requests and be shared between workers.
    |
    */

    'cache' => [
        // Cache store to use for tokens (null uses default)
        'store' => env('TUBAPAY_CACHE_STORE'),

        // Cache key prefix
        'prefix' => 'tubapay_',

        // Token expiration buffer in seconds (refresh before actual expiry)
        'expiration_buffer' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Configuration for transaction tracking in the database.
    |
    */

    'database' => [
        // Whether to track transactions in the database
        'track_transactions' => env('TUBAPAY_TRACK_TRANSACTIONS', true),

        // The database connection to use (null uses default)
        'connection' => env('TUBAPAY_DB_CONNECTION'),

        // The table name for storing transactions
        'table' => 'tubapay_transactions',

        // The table name for storing temporary checkout selections
        'checkout_selections_table' => 'tubapay_checkout_selections',

        // The table name for storing merchant payment notifications
        'payments_table' => 'tubapay_payments',

        // The table name for storing recurring order requests
        'recurring_requests_table' => 'tubapay_recurring_requests',

        // The table name for storing webhook idempotency state
        'webhook_events_table' => 'tubapay_webhook_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Mapping
    |--------------------------------------------------------------------------
    |
    | Optional application status mapping. The package does not update host
    | application orders directly, but this map gives listeners a shared helper.
    |
    */

    'status_map' => [
        'draft' => null,
        'registered' => null,
        'signed' => null,
        'accepted' => null,
        'rejected' => null,
        'canceled' => null,
        'terminated' => null,
        'withdrew' => null,
        'repaid' => null,
        'closed' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for TubaPay operations.
    |
    */

    'logging' => [
        // Log channel to use (null uses default)
        'channel' => env('TUBAPAY_LOG_CHANNEL'),

        // Whether to log webhook payloads
        'log_webhooks' => env('TUBAPAY_LOG_WEBHOOKS', false),

        // Whether to log API requests
        'log_requests' => env('TUBAPAY_LOG_REQUESTS', false),
    ],
];
