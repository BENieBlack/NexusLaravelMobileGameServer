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
        $baseDir = __DIR__.'/../database/migrations';
        
        // 各サブディレクトリを個別に読み込む
        foreach (['mst', 'trx', 'log', 'sys'] as $type) {
            $path = "{$baseDir}/{$type}";
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
