<?php

namespace NexusLogin;

use Illuminate\Support\ServiceProvider;

/**
 * Login Service Provider
 */
class LoginServiceProvider extends ServiceProvider
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
