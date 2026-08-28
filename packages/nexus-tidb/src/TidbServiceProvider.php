<?php

namespace NexusTidb;

use Illuminate\Support\ServiceProvider;

/**
 * TidbServiceProvider
 *
 * TiDB対応パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - Concerns: UsesUuidPrimaryKey（単一主キーidをUUIDで払い出すtrait）
 * - Support: TidbMode（TiDBとして扱うかの判定）
 */
class TidbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nexus-tidb.php', 'nexus-tidb');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nexus-tidb.php' => config_path('nexus-tidb.php'),
        ], 'nexus-tidb-config');
    }
}
