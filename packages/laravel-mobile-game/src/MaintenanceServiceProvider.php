<?php

namespace LaravelMobileGame;

use LaravelMobileGame\Contracts\MaintenanceStorageInterface;
use LaravelMobileGame\Infrastructure\DynamoDB\DynamoDBMaintenanceStorage;
use LaravelMobileGame\Infrastructure\TableStore\TableStoreMaintenanceStorage;
use LaravelMobileGame\Services\MaintenanceService;
use Illuminate\Support\ServiceProvider;

/**
 * メンテナンスサービスプロバイダー
 * 
 * メンテナンス機能の依存性注入を設定
 */
class MaintenanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/maintenance.php', 'maintenance'
        );

        // ストレージ実装のバインド
        $this->app->singleton(MaintenanceStorageInterface::class, function ($app) {
            $driver = config('maintenance.driver', 'dynamodb');

            return match ($driver) {
                'dynamodb' => $this->createDynamoDBStorage(),
                'tablestore' => $this->createTableStoreStorage(),
                default => throw new \InvalidArgumentException("Unsupported maintenance driver: {$driver}"),
            };
        });

        // MaintenanceServiceのバインド
        $this->app->singleton(MaintenanceService::class, function ($app) {
            return new MaintenanceService(
                storage: $app->make(MaintenanceStorageInterface::class),
                cacheTtl: config('maintenance.cache.ttl', 60),
                cacheEnabled: config('maintenance.cache.enabled', true),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 設定ファイルをpublish可能にする
        $this->publishes([
            __DIR__.'/../config/maintenance.php' => config_path('maintenance.php'),
        ], 'config');
    }

    /**
     * DynamoDB ストレージを作成
     */
    private function createDynamoDBStorage(): DynamoDBMaintenanceStorage
    {
        if (!class_exists('Aws\DynamoDb\DynamoDbClient')) {
            throw new \RuntimeException(
                'AWS SDK is required for DynamoDB storage. Install it with: composer require aws/aws-sdk-php'
            );
        }

        return new DynamoDBMaintenanceStorage(config('maintenance.dynamodb'));
    }

    /**
     * TableStore ストレージを作成
     */
    private function createTableStoreStorage(): TableStoreMaintenanceStorage
    {
        if (!class_exists('Aliyun\OTS\OTSClient')) {
            throw new \RuntimeException(
                'Alibaba Cloud TableStore SDK is required for TableStore storage. Install it with: composer require aliyun/aliyun-tablestore-sdk-php'
            );
        }

        return new TableStoreMaintenanceStorage(config('maintenance.tablestore'));
    }
}
