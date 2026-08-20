<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | アプリ内課金。認証情報が無いと購入検証が失敗する（既定値は設けない）
    */

    'google_play' => [
        // アプリのパッケージ名（例: com.example.nexus）
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        // サービスアカウントJSONのパス、またはJSON文字列そのもの
        'service_account' => env('GOOGLE_PLAY_SERVICE_ACCOUNT'),
    ],

    'app_store' => [
        // App Store Connect の共有シークレット（/verifyReceipt 用）
        'shared_secret' => env('APP_STORE_SHARED_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
