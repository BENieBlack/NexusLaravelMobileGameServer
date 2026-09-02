<?php

namespace NexusChat;

use Illuminate\Support\ServiceProvider;

/**
 * ChatServiceProvider
 *
 * マイグレーションのロードのみ担当。
 * DIバインディングはApplication層のAppServiceProviderで行う。
 * （ChatRoomRepositoryInterface 等の実装は Eloquent に依存するため）
 */
class ChatServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $baseDir = __DIR__.'/../database/migrations';

        foreach (['sys'] as $type) {
            $path = "{$baseDir}/{$type}";
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
