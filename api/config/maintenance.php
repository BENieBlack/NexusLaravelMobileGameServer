<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maintenance Configuration
    |--------------------------------------------------------------------------
    |
    | メンテナンスモードの設定
    |
    */

    // メンテナンス機能の有効/無効
    'enabled' => env('MAINTENANCE_ENABLED', true),

    // ストレージドライバー: dynamodb, tablestore
    'driver' => env('MAINTENANCE_DRIVER', 'dynamodb'),

    /*
    |--------------------------------------------------------------------------
    | AWS DynamoDB Configuration
    |--------------------------------------------------------------------------
    */
    'dynamodb' => [
        'region' => env('AWS_REGION', 'ap-northeast-1'),
        'table' => env('MAINTENANCE_DYNAMODB_TABLE', 'maintenance_status'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alibaba Cloud TableStore Configuration
    |--------------------------------------------------------------------------
    */
    'tablestore' => [
        'endpoint' => env('TABLESTORE_ENDPOINT'),
        'instance' => env('TABLESTORE_INSTANCE'),
        'table' => env('MAINTENANCE_TABLESTORE_TABLE', 'maintenance_status'),
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Laravelローカルキャッシュで高速化
    |
    */
    'cache' => [
        'enabled' => env('MAINTENANCE_CACHE_ENABLED', true),
        'ttl' => env('MAINTENANCE_CACHE_TTL', 60), // 秒
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded IPs
    |--------------------------------------------------------------------------
    |
    | メンテナンス中でもアクセス可能なIPアドレス（カンマ区切り）
    |
    */
    'excluded_ips' => array_filter(
        explode(',', env('MAINTENANCE_EXCLUDED_IPS', ''))
    ),
];
