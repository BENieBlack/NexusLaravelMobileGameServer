<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client Signature Verification
    |--------------------------------------------------------------------------
    |
    | クライアント署名検証の設定
    |
    */
    'client_signature' => [
        'enabled' => env('CLIENT_SIGNATURE_ENABLED', true),
        'secret' => env('CLIENT_SECRET'),
        'timestamp_tolerance' => env('CLIENT_SIGNATURE_TIMESTAMP_TOLERANCE', 300), // 5分
        'nonce_cache_ttl' => env('CLIENT_SIGNATURE_NONCE_CACHE_TTL', 600), // 10分
        'skip_in_local' => env('CLIENT_SIGNATURE_SKIP_IN_LOCAL', false), // ローカル環境でも検証する
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | 冪等性保証の設定
    |
    */
    'idempotency' => [
        'enabled' => env('IDEMPOTENCY_ENABLED', true),
        'cache_prefix' => env('IDEMPOTENCY_CACHE_PREFIX', 'idempotency'),
        'cache_ttl' => env('IDEMPOTENCY_CACHE_TTL', 86400), // 24時間
        'compression_level' => env('IDEMPOTENCY_COMPRESSION_LEVEL', 6), // 1-9
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle Sign Up
    |--------------------------------------------------------------------------
    |
    | サインアップのレート制限設定
    |
    */
    'throttle_signup' => [
        'enabled' => env('THROTTLE_SIGNUP_ENABLED', true),
        'max_attempts_per_ip' => env('THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_IP', 10),
        'max_attempts_per_device' => env('THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_DEVICE', 3),
        'rate_limit_window' => env('THROTTLE_SIGNUP_RATE_LIMIT_WINDOW', 3600), // 1時間
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle Auth
    |--------------------------------------------------------------------------
    |
    | sign_in / refresh_token のレート制限設定
    | クレデンシャルスタッフィングやトークン総当たりを抑制する
    |
    */
    'throttle_auth' => [
        'enabled' => env('THROTTLE_AUTH_ENABLED', true),
        'max_attempts_per_ip' => env('THROTTLE_AUTH_MAX_ATTEMPTS_PER_IP', 30),
        'max_attempts_per_credential' => env('THROTTLE_AUTH_MAX_ATTEMPTS_PER_CREDENTIAL', 10),
        'rate_limit_window' => env('THROTTLE_AUTH_RATE_LIMIT_WINDOW', 900), // 15分
    ],
];
