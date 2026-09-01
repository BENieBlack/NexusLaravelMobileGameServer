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
        // マイグレーションをロード（各データベース用に分割）
        $basePath = __DIR__.'/../database/migrations';

        // mst, trx, log, sys の各ディレクトリを個別にロード
        foreach (['mst', 'trx', 'log', 'sys'] as $dbType) {
            $path = $basePath.'/'.$dbType;
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
