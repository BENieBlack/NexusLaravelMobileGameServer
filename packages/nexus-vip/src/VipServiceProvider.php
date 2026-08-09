<?php

namespace NexusVip;

use Illuminate\Support\ServiceProvider;
use NexusVip\Contracts\CurrencyConverterInterface;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipLevelRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use NexusVip\Services\CurrencyConverter;
use NexusVip\Services\VipBenefitService;
use NexusVip\Services\VipLevelService;
use NexusVip\Services\VipPointService;

/**
 * VIPシステムサービスプロバイダー
 */
class VipServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/vip.php',
            'vip'
        );

        // インターフェースと実装のバインド
        // Note: Repository実装クラスは api/app/Repositories に配置されるため、
        // ここではインターフェースのみ定義し、実装は AppServiceProvider でバインドする
        
        // 通貨換算サービス
        $this->app->singleton(CurrencyConverterInterface::class, CurrencyConverter::class);
        
        // VIPレベルサービス
        $this->app->singleton(VipLevelService::class, function ($app) {
            return new VipLevelService(
                $app->make(VipLevelRepositoryInterface::class)
            );
        });
        
        // VIPポイントサービス
        $this->app->singleton(VipPointService::class, function ($app) {
            return new VipPointService(
                $app->make(PlayerVipRepositoryInterface::class),
                $app->make(VipPointLogRepositoryInterface::class),
                $app->make(VipLevelService::class),
                $app->make(CurrencyConverterInterface::class)
            );
        });
        
        // VIP特典サービス
        $this->app->singleton(VipBenefitService::class, function ($app) {
            return new VipBenefitService(
                $app->make(VipLevelService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // マイグレーションをロード（動的シャーディング対応）
        // 注意: php artisan pitr:migrate で全LogDBシャード（log1, log2, ...）に実行
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // 設定ファイルの公開
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/vip.php' => config_path('vip.php'),
            ], 'vip-config');
        }
    }
}
