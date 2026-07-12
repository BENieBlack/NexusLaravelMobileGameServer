<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App Store Configuration
    |--------------------------------------------------------------------------
    |
    | Apple App Store receipt verification settings
    |
    */
    'app_store' => [
        // Shared secret for receipt verification
        // Get this from App Store Connect
        'shared_secret' => env('APP_STORE_SHARED_SECRET', ''),

        // Use sandbox environment for testing
        'use_sandbox' => env('APP_STORE_USE_SANDBOX', env('APP_ENV') !== 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Play Configuration
    |--------------------------------------------------------------------------
    |
    | Google Play Developer API settings
    |
    */
    'google_play' => [
        // Your app's package name (e.g., com.example.app)
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', ''),

        // Service account JSON file path
        // Download from Google Cloud Console
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON', storage_path('app/google-play-service-account.json')),

        // Alternatively, use service account JSON content directly
        'service_account_json_content' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON_CONTENT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for duplicate purchase prevention
    |
    */
    'idempotency' => [
        // Cache TTL in seconds (default: 24 hours)
        'cache_ttl' => env('BILLING_IDEMPOTENCY_TTL', 86400),

        // Cache key prefix
        'cache_prefix' => env('BILLING_IDEMPOTENCY_PREFIX', 'billing:idempotency:'),

        // Cache driver (null = use default)
        'cache_driver' => env('BILLING_IDEMPOTENCY_CACHE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Control what billing events are logged
    |
    */
    'logging' => [
        // Log successful verifications
        'log_success' => env('BILLING_LOG_SUCCESS', true),

        // Log failed verifications
        'log_failures' => env('BILLING_LOG_FAILURES', true),

        // Log duplicate purchase attempts
        'log_duplicates' => env('BILLING_LOG_DUPLICATES', true),
    ],
];
