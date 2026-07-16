<?php

namespace App\Providers;

use App\Domain\Auth\Services\TokenValidator;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use App\Persistence\ApiSession;
use NexusSecurity\Contracts\TokenValidatorInterface;
use NexusSecurity\Contracts\PlayerSessionInterface;
use NexusUnitOfWork\Contracts\PlayerSessionResolverInterface;
use NexusUnitOfWork\Contracts\QueryManagerInterface as UnitOfWorkQueryManagerInterface;
use NexusUnitOfWork\Persistence\QueryManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ResourceDeliveryManager のバインディング
        // リクエストスコープ: 各リクエストごとに新しいインスタンスを生成
        // 配送待ちコンテンツはリクエスト内でのみ保持される
        $this->app->bind(
            ResourceDeliveryManagerInterface::class,
            ResourceDeliveryManager::class
        );

        // Unit of Work パターン用のQueryManagerをシングルトンとして登録
        $this->app->singleton(QueryManager::class);
        $this->app->singleton('query.manager', QueryManager::class);
        
        // Unit of Work パッケージのQueryManagerInterfaceもバインド
        $this->app->singleton(UnitOfWorkQueryManagerInterface::class, QueryManager::class);

        // セキュリティミドルウェアパッケージ用のインターフェースバインディング
        $this->app->bind(TokenValidatorInterface::class, TokenValidator::class);
        $this->app->bind(PlayerSessionInterface::class, ApiSession::class);
        
        // Unit of Work パッケージ用のPlayerSessionResolverInterfaceバインディング
        $this->app->bind(PlayerSessionResolverInterface::class, ApiSession::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for database
        Schema::defaultStringLength(191);

        // Load migrations from subdirectories
        $this->loadMigrationsFrom([
            database_path('migrations/mst'),
            database_path('migrations/trx'),
            database_path('migrations/sys'),
            database_path('migrations/adm'),
            database_path('migrations/log'),
        ]);
    }
}
