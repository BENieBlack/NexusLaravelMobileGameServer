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

    // ストレージドライバー: database, dynamodb, tablestore
    // databaseは外部SDK不要でsys_maintenanceテーブルを使う（ローカル開発の既定）
    'driver' => env('MAINTENANCE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('MAINTENANCE_DB_CONNECTION', 'sys'),
        'table' => env('MAINTENANCE_DB_TABLE', 'sys_maintenance'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS DynamoDB Configuration
    |--------------------------------------------------------------------------
    */
    'dynamodb' => [
        'region' => env('AWS_DYNAMODB_REGION', env('AWS_REGION', 'ap-northeast-1')),
        'table' => env('AWS_DYNAMODB_MAINTENANCE_TABLE', 'sys_maintenance'),
        'primary_key' => env('AWS_DYNAMODB_MAINTENANCE_PRIMARY_KEY', 'SysMaintenance'),
        'key' => env('AWS_DYNAMODB_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_DYNAMODB_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        'endpoint' => env('AWS_DYNAMODB_ENDPOINT'), // ローカル開発用
    ],

    /*
    |--------------------------------------------------------------------------
    | Alibaba Cloud TableStore Configuration
    |--------------------------------------------------------------------------
    */
    'tablestore' => [
        'endpoint' => env('ALIBABA_TABLESTORE_ENDPOINT'),
        'instance' => env('ALIBABA_TABLESTORE_INSTANCE'),
        'table' => env('ALIBABA_TABLESTORE_MAINTENANCE_TABLE', 'sys_maintenance'),
        'primary_key' => env('ALIBABA_TABLESTORE_MAINTENANCE_PRIMARY_KEY', 'SysMaintenance'),
        'access_key_id' => env('ALIBABA_TABLESTORE_ACCESS_KEY_ID', env('ALIBABA_ACCESS_KEY_ID')),
        'access_key_secret' => env('ALIBABA_TABLESTORE_ACCESS_KEY_SECRET', env('ALIBABA_ACCESS_KEY_SECRET')),
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

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | メンテナンス中でもアクセス可能なルート（URIパターン）
    | ワイルドカード（*）を使用可能
    |
    */
    'excluded_routes' => [
        // バージョンチェック（メンテナンス情報を含む）
        'auth/version',

        // メンテナンス状態確認
        'maintenance/status',

        // 管理者用メンテナンス操作API
        'admin/maintenance/*',

        // ヘルスチェック
        'up',
    ],
];
