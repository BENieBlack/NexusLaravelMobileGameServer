<?php

namespace LaravelWallet;

use Illuminate\Support\ServiceProvider;
use LaravelWallet\Contracts\WalletManagerInterface;
use LaravelWallet\Services\WalletService;

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
        $this->app->singleton(WalletService::class);
        $this->app->singleton(WalletManagerInterface::class, WalletService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // マイグレーションをロード（動的シャーディング対応）
        // 注意: php artisan trx:migrate で全TrxDBシャード（trx1, trx2, ...）に実行
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // 設定ファイルの公開
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wallet.php' => config_path('wallet.php'),
            ], 'wallet-config');
        }
    }
}
