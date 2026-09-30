<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        // Master DB connection
        'mst' => [
            'driver' => 'mysql',
            'url' => env('DB_MASTER_URL'),
            'host' => env('DB_MASTER_HOST', 'db-master'),
            'port' => env('DB_MASTER_PORT', '3306'),
            'database' => env('DB_MASTER_DATABASE') ?: env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local').'-master',
            'username' => env('DB_MASTER_USERNAME', 'root'),
            'password' => env('DB_MASTER_PASSWORD', ''),
            'unix_socket' => env('MASTER_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ========================================
        // 動的シャーディング: shard DB
        // ========================================
        // DB_SHARD_COUNT環境変数でシャード数を指定（デフォルト: 2）
        // 例: DB_SHARD_COUNT=4 の場合、trx1, trx2, trx3, trx4 を生成
        ...(function () {
            $shardCount = (int) env('DB_SHARD_COUNT', 2);
            $connections = [];

            for ($i = 1; $i <= $shardCount; $i++) {
                $connections["trx{$i}"] = [
                    'driver' => 'mysql',
                    // DB_SHARD_* はシャード共通のベース。ホストとDB名にはシャード番号を付ける
                    // （DB_SHARD_HOST=db-shard → db-shard1, db-shard2, ...）
                    // シャードごとに変える場合のみ DB_TRX{N}_* で上書きする
                    'host' => env("DB_SHARD{$i}_HOST") ?? env('DB_SHARD_HOST', 'db-shard').$i,
                    'port' => env("DB_SHARD{$i}_PORT") ?? env('DB_SHARD_PORT', '3306'),
                    'database' => env("DB_SHARD{$i}_DATABASE") ?: (env('DB_SHARD_DATABASE') ?: env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local').'-shard'),
                    'username' => env("DB_SHARD{$i}_USERNAME") ?? env('DB_SHARD_USERNAME', 'root'),
                    'password' => env("DB_SHARD{$i}_PASSWORD") ?? env('DB_SHARD_PASSWORD', 'root'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ];
            }

            return $connections;
        })(),

        // システムDB接続
        'sys' => [
            'driver' => 'mysql',
            'host' => env('DB_SYSTEM_HOST', 'db-system'),
            'port' => env('DB_SYSTEM_PORT', '3306'),
            'database' => env('DB_SYSTEM_DATABASE') ?: env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local').'-system',
            'username' => env('DB_SYSTEM_USERNAME', 'root'),
            'password' => env('DB_SYSTEM_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        // 管理DB接続
        'adm' => [
            'driver' => 'mysql',
            'host' => env('DB_ADMIN_HOST', 'db-admin'),
            'port' => env('DB_ADMIN_PORT', '3306'),
            'database' => env('DB_ADMIN_DATABASE') ?: env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local').'-adm',
            'username' => env('DB_ADMIN_USERNAME', 'root'),
            'password' => env('DB_ADMIN_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        // ========================================
        // テスト用エイリアス接続
        // ========================================
        // テストコードの互換性のため、trxという名前でtrx1を参照
        'trx' => [
            'driver' => 'mysql',
            'host' => env('DB_SHARD1_HOST') ?? env('DB_SHARD_HOST', 'db-shard').'1',
            'port' => env('DB_SHARD1_PORT') ?? env('DB_SHARD_PORT', '3306'),
            'database' => env('DB_SHARD1_DATABASE') ?: (env('DB_SHARD_DATABASE') ?: env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local').'-shard'),
            'username' => env('DB_SHARD1_USERNAME') ?? env('DB_SHARD_USERNAME', 'root'),
            'password' => env('DB_SHARD1_PASSWORD') ?? env('DB_SHARD_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PITR (Point-In-Time Recovery) Configuration
    |--------------------------------------------------------------------------
    |
    | TrxDB故障時のポイントインタイムリカバリー設定
    | shard_count: TrxDB/LogDBのシャード数（DB_SHARD_COUNTと同期）
    | active_trx_connections: トランザクションで使用するTrxDB接続のリスト
    |
    */
    'pitr' => [
        'shard_count' => (int) env('DB_SHARD_COUNT', 2),
        'active_trx_connections' => (function () {
            $shardCount = (int) env('DB_SHARD_COUNT', 2);
            $connections = [];
            for ($i = 1; $i <= $shardCount; $i++) {
                $connections[] = "trx{$i}";
            }

            return $connections;
        })(),
        'batch_size' => env('PITR_BATCH_SIZE', 1000),
        'enable_compression' => env('PITR_ENABLE_COMPRESSION', false),
    ],
];
