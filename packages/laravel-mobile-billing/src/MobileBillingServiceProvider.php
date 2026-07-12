<?php

namespace LaravelMobileBilling;

use Illuminate\Support\ServiceProvider;
use LaravelMobileBilling\ApiClients\AppStoreApiClient;
use LaravelMobileBilling\ApiClients\GooglePlayApiClient;
use LaravelMobileBilling\Contracts\BillingPlatformInterface;
use LaravelMobileBilling\Facades\BillingFacade;
use LaravelMobileBilling\Services\AppStoreBillingService;
use LaravelMobileBilling\Services\BillingPlatformFactory;
use LaravelMobileBilling\Services\GooglePlayBillingService;
use LaravelMobileBilling\Services\IdempotencyService;

/**
 * Mobile Billing Service Provider
 * 
 * パッケージのサービス登録と初期化を担当
 */
class MobileBillingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/mobile-billing.php', 'mobile-billing'
        );

        // API Clients
        $this->app->singleton(AppStoreApiClient::class, function ($app) {
            return new AppStoreApiClient();
        });

        $this->app->singleton(GooglePlayApiClient::class, function ($app) {
            return new GooglePlayApiClient();
        });

        // Platform Services
        $this->app->singleton(AppStoreBillingService::class, function ($app) {
            return new AppStoreBillingService(
                $app->make(AppStoreApiClient::class)
            );
        });

        $this->app->singleton(GooglePlayBillingService::class, function ($app) {
            return new GooglePlayBillingService(
                $app->make(GooglePlayApiClient::class)
            );
        });

        // Factory
        $this->app->singleton(BillingPlatformFactory::class, function ($app) {
            return new BillingPlatformFactory(
                $app->make(AppStoreBillingService::class),
                $app->make(GooglePlayBillingService::class)
            );
        });

        // Idempotency Service
        $this->app->singleton(IdempotencyService::class, function ($app) {
            return new IdempotencyService();
        });

        // Facade
        $this->app->singleton(BillingFacade::class, function ($app) {
            return new BillingFacade(
                $app->make(BillingPlatformFactory::class),
                $app->make(IdempotencyService::class)
            );
        });

        // Alias for easier access
        $this->app->alias(BillingFacade::class, 'mobile-billing');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 設定ファイルの公開
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mobile-billing.php' => config_path('mobile-billing.php'),
            ], 'mobile-billing-config');
        }
    }
}
