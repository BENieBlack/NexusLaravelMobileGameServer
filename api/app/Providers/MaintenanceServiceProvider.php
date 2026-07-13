<?php

namespace App\Providers;

use App\Contracts\Maintenance\MaintenanceStorageInterface;
use App\Infrastructure\Maintenance\DynamoDBMaintenanceStorage;
use App\Infrastructure\Maintenance\TableStoreMaintenanceStorage;
use App\Services\MaintenanceService;
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
        // ストレージ実装のバインド
        $this->app->singleton(MaintenanceStorageInterface::class, function ($app) {
            $driver = config('maintenance.driver', 'dynamodb');

            return match ($driver) {
                'dynamodb' => new DynamoDBMaintenanceStorage(
                    config('maintenance.dynamodb')
                ),
                'tablestore' => new TableStoreMaintenanceStorage(
                    config('maintenance.tablestore')
                ),
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
        //
    }
}
