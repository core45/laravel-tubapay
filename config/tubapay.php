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
