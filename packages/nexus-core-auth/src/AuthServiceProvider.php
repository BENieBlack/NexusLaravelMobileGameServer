<?php

namespace NexusAuth;

use Illuminate\Support\ServiceProvider;

/**
 * Auth Service Provider
 *
 * 認証で使うテーブル（sys_player_token）のマイグレーションを読み込む。
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $baseDir = __DIR__.'/../database/migrations';

        foreach (['mst', 'trx', 'log', 'sys'] as $type) {
            $path = "{$baseDir}/{$type}";
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
