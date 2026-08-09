<?php

namespace NexusResource;

use Illuminate\Support\ServiceProvider;

/**
 * Resource Service Provider
 */
class ResourceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // マイグレーションをロード（動的シャーディング対応）
        // 注意: php artisan trx:migrate で全TrxDBシャード（trx1, trx2, ...）に実行
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
