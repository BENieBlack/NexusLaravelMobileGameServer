<?php

namespace NexusVip;

use Illuminate\Support\ServiceProvider;
use NexusVip\Contracts\CurrencyConverterInterface;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipLevelRepositoryInterface;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use NexusVip\Services\CurrencyConverter;
use NexusVip\Services\VipBenefitService;
use NexusVip\Services\VipLevelService;
use NexusVip\Services\VipPointService;
use NexusVip\Services\VipRewardService;
use NexusVip\ValueObjects\VipConfig;

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

        // VIP設定Value Objectをシングルトンとして登録
        // Application層でconfig()を読み込み、Package層に渡す
        $this->app->singleton(VipConfig::class, function ($app) {
            return new VipConfig(
                enablePointLog: config('vip.enable_point_log', true),
                enableLevelUpEvent: config('vip.enable_level_up_event', true),
                staminaBonusEnabled: config('vip.benefits_enabled.stamina_bonus', true),
                shopDiscountEnabled: config('vip.benefits_enabled.shop_discount', true),
                gachaDiscountEnabled: config('vip.benefits_enabled.gacha_discount', true),
                dailyDiamondEnabled: config('vip.benefits_enabled.daily_diamond', true),
            );
        });

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

        // VIP報酬サービス
        $this->app->singleton(VipRewardService::class, function ($app) {
            return new VipRewardService(
                $app->make(VipLevelRewardRepositoryInterface::class)
            );
        });

        // VIPポイントサービス
        $this->app->singleton(VipPointService::class, function ($app) {
            return new VipPointService(
                $app->make(PlayerVipRepositoryInterface::class),
                $app->make(VipPointLogRepositoryInterface::class),
                $app->make(VipLevelService::class),
                $app->make(VipRewardService::class),
                $app->make(VipConfig::class)
            );
        });

        // VIP特典サービス
        $this->app->singleton(VipBenefitService::class, function ($app) {
            return new VipBenefitService(
                $app->make(VipLevelService::class),
                $app->make(VipConfig::class)
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
