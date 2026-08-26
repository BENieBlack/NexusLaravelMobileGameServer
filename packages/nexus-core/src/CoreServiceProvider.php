<?php

namespace Nexus\Core;

use Illuminate\Support\ServiceProvider;

/**
 * CoreServiceProvider
 * 
 * Nexus Coreパッケージのサービスプロバイダー
 * 
 * 提供機能:
 * - Models: Eloquent Model基底クラス（_BaseModel, _BaseTrx, _BaseSys, _BaseMst, _BaseLog）
 * - Repositories: Repository基底クラス、プレイヤー関連のRepositoryインターフェース
 * - Contracts: プレイヤー／デバイスのModelインターフェース
 * - DataTransferObjects: Player
 * - Support: CustomCollection等
 * - Utilities: ClockUtility
 * - ValueObjects: ErrorResponse等
 *
 * プレイヤーはこのフレームワークの前提（trx/logの行は全て sys_player_id で引く）
 * なので、実体とそのテーブルもこのパッケージが持つ。
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nexus-core.php', 'nexus-core');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // マイグレーションをロード（動的シャーディング対応）
        // 注意: php artisan trx:migrate で全TrxDBシャード（trx1, trx2, ...）に実行
        $this->publishes([
            __DIR__.'/../config/nexus-core.php' => config_path('nexus-core.php'),
        ], 'nexus-core-config');

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
