<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Unit of Work パターンで使用するデータベース接続名の設定
    |
    */
    'connections' => [
        'trx' => env('DB_CONNECTION_TRX', 'trx'),
        'log' => env('DB_CONNECTION_LOG', 'log'),
        'sys' => env('DB_CONNECTION_SYS', 'sys'),
        'mst' => env('DB_CONNECTION_MST', 'mst'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | リポジトリのキャッシュ設定
    |
    */
    'cache' => [
        'driver' => env('UNIT_OF_WORK_CACHE_DRIVER', 'redis'),
        'ttl' => env('UNIT_OF_WORK_CACHE_TTL', 3600), // 秒
        'prefix' => env('UNIT_OF_WORK_CACHE_PREFIX', 'uow'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Execution Settings
    |--------------------------------------------------------------------------
    |
    | QueryManager のバッチ実行に関する設定
    |
    */
    'batch' => [
        // バッチINSERTの最大行数（MySQLの max_allowed_packet を考慮）
        'max_insert_rows' => env('UNIT_OF_WORK_BATCH_INSERT_MAX', 1000),

        // バッチUPDATEの最大行数
        'max_update_rows' => env('UNIT_OF_WORK_BATCH_UPDATE_MAX', 1000),

        // トランザクションタイムアウト（秒）
        'transaction_timeout' => env('UNIT_OF_WORK_TRANSACTION_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Logging
    |--------------------------------------------------------------------------
    |
    | QueryManager の実行クエリログ設定
    |
    */
    'logging' => [
        'enabled' => env('UNIT_OF_WORK_LOGGING_ENABLED', false),
        'channel' => env('UNIT_OF_WORK_LOG_CHANNEL', 'stack'),
    ],
];
