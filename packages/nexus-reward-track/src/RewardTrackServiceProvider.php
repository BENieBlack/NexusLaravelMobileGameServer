<?php

namespace NexusRewardTrack;

use Illuminate\Support\ServiceProvider;

/**
 * RewardTrackServiceProvider
 *
 * マイグレーションのロードを担当。
 * DIバインディングは Application 層の AppServiceProvider で行う。
 */
class RewardTrackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // DIバインドは api/app/Providers/AppServiceProvider で実施
    }

    public function boot(): void
    {
        $baseDir = __DIR__.'/../database/migrations';

        foreach (['mst', 'trx'] as $type) {
            $path = "{$baseDir}/{$type}";
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
