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
    | Throttle Public Read
    |--------------------------------------------------------------------------
    |
    | 認証不要の参照系エンドポイントのレート制限設定
    |
    */
    'throttle_public_read' => [
        'enabled' => env('THROTTLE_PUBLIC_READ_ENABLED', true),
        'max_attempts_per_ip' => env('THROTTLE_PUBLIC_READ_MAX_ATTEMPTS_PER_IP', 60),
        'rate_limit_window' => env('THROTTLE_PUBLIC_READ_RATE_LIMIT_WINDOW', 60), // 1分
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Error Notification
    |--------------------------------------------------------------------------
    |
    | APIエラー発生時のSlack通知設定
    | SLACK_ERROR_POST_CHANNEL: 通知先チャンネルID
    | SLACK_BOT_TOKEN: SlackのBotトークン
    |
    | ignore_error_codes:
    |   Slack通知をしないエラーコードの配列。
    |   ユーザー起因の想定内エラー（認証失敗・入力ミス等）は除外することで
    |   通知のノイズを減らせる。
    |   例: [10001, 10002, 10003]
    |
    */
    'slack_error_channel' => env('SLACK_ERROR_POST_CHANNEL'),
    'slack_bot_token' => env('SLACK_BOT_TOKEN'),
    'slack_ignore_error_codes' => [],
];
