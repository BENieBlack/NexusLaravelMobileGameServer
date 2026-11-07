<?php

namespace Nexus\Core;

use Illuminate\Support\ServiceProvider;
use Nexus\Core\Pitr\Commands\PitrMigrateCommand;
use Nexus\Core\Pitr\Commands\PitrRollbackCommand;
use Nexus\Core\Pitr\Commands\TrxMigrateCommand;
use Nexus\Core\Pitr\Commands\TrxRollbackCommand;
use Nexus\Core\Pitr\Logger\TrxChangeLogger;
use Nexus\Core\UnitOfWork\Contracts\QueryManagerInterface;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;

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
 * - Resource / ResourceDelivery: 報酬（リソース）の表現と配布
 * - UnitOfWork: 書き込みをキューに積み、トランザクションの終わりにまとめて実行する（QueryManager）
 * - Pitr: trx の変更ログ（PITR）と、シャードごとのマイグレーション・ロールバックのコマンド
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
        $this->mergeConfigFrom(__DIR__.'/../config/nexus-pitr.php', 'nexus-pitr');
        $this->mergeConfigFrom(__DIR__.'/../config/unit-of-work.php', 'unit-of-work');

        // PITR: TrxChangeLoggerをシングルトンとして登録
        $this->app->singleton(TrxChangeLogger::class, function ($app) {
            return new TrxChangeLogger;
        });

        // UnitOfWork: QueryManagerをscopedバインドにする（リクエストスコープ）。
        // singleton だとリクエスト間でキューが混在し、メモリリークの原因になる
        $this->app->scoped(QueryManagerInterface::class, function ($app) {
            return new QueryManager;
        });

        // QueryManagerクラスとしても登録（後方互換性のため）
        $this->app->scoped(QueryManager::class, function ($app) {
            return $app->make(QueryManagerInterface::class);
        });
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

        $this->publishes([
            __DIR__.'/../config/nexus-pitr.php' => config_path('nexus-pitr.php'),
        ], 'nexus-pitr-config');

        $this->publishes([
            __DIR__.'/../config/unit-of-work.php' => config_path('unit-of-work.php'),
        ], 'unit-of-work-config');

        // Artisanコマンドを登録（シャードごとのマイグレーション・ロールバック）
        if ($this->app->runningInConsole()) {
            $this->commands([
                PitrMigrateCommand::class,
                PitrRollbackCommand::class,
                TrxMigrateCommand::class,
                TrxRollbackCommand::class,
            ]);
        }

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
