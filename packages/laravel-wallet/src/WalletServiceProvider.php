<?php

namespace LaravelWallet;

use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 設定ファイルの公開
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wallet.php' => config_path('wallet.php'),
            ], 'wallet-config');
        }
    }
}
