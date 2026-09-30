<?php

namespace NexusWallet;

use Illuminate\Support\ServiceProvider;
use NexusWallet\Contracts\WalletManagerInterface;
use NexusWallet\Services\WalletService;

/**
 * Wallet Service Provider
 *
 * パッケージのサービス登録と初期化を担当
 */
class WalletServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/wallet.php', 'wallet'
        );

        // WalletService を WalletManagerInterface として登録
        // Application層でRepositoryの実装を提供する必要がある
        //
        // singletonではなくscopedを使う。WalletServiceはRepositoryを
        // コンストラクタで受け取って持ち続けるため、singletonにすると
        // OctaneやキューワーカーでリクエストをまたいでRepositoryが残り、
        // 別プレイヤーの残高キャッシュを持ち越してしまう。
        // Repository側をscopedにしている意味が無くなる
        $this->app->scoped(WalletService::class);
        $this->app->scoped(WalletManagerInterface::class, WalletService::class);
    }

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

        // 設定ファイルの公開
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wallet.php' => config_path('wallet.php'),
            ], 'wallet-config');
        }
    }
}
