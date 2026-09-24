<?php

namespace NexusNotification;

use Illuminate\Support\ServiceProvider;

/**
 * NotificationServiceProvider
 *
 * マイグレーションのロードのみ担当。
 * DIバインディングはApplication層のAppServiceProviderで行う。
 * （NotificationRepositoryInterfaceの実装はEloquentに依存するため）
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $baseDir = __DIR__.'/../database/migrations';

        foreach (['sys', 'trx'] as $type) {
            $path = "{$baseDir}/{$type}";
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
