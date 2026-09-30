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

        // App Store Server API の接続先（production / sandbox）
        'environment' => env('APP_STORE_ENVIRONMENT', 'sandbox'),

        /*
        | App Store Server API の認証（ES256のJWT）
        |
        | App Store Connect の「Keys > In-App Purchase」で発行した鍵を使う。
        | private_key は .p8 ファイルのパス、またはPEM文字列そのもの。
        */
        'jwt' => [
            'key_id' => env('APP_STORE_JWT_KEY_ID'),
            'issuer_id' => env('APP_STORE_JWT_ISSUER_ID'),
            'bundle_id' => env('APP_STORE_JWT_BUNDLE_ID'),
            'private_key' => env('APP_STORE_JWT_PRIVATE_KEY'),
            // 有効期間（秒）。Appleの上限は3600秒
            'ttl' => (int) env('APP_STORE_JWT_TTL', 1800),
            // 署名検証に使うApple Root CA - G3。未設定なら証明書チェーンの検証を行わない
            'root_certificate' => env('APP_STORE_JWT_ROOT_CERTIFICATE'),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
