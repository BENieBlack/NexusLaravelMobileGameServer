<?php

namespace App\Providers;

use App\Domain\Auth\Services\TokenValidator;
use App\Domain\Delivery\Managers\DeliveryManager;
use App\Domain\Delivery\Managers\DeliveryManagerInterface;
use App\Persistence\ApiSession;
use LaravelSecurityMiddleware\Contracts\TokenValidatorInterface;
use LaravelSecurityMiddleware\Contracts\PlayerSessionInterface;
use LaravelUnitOfWork\Contracts\PlayerSessionResolverInterface;
use LaravelUnitOfWork\Contracts\QueryManagerInterface as UnitOfWorkQueryManagerInterface;
use LaravelUnitOfWork\Persistence\QueryManager;
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
        // DeliveryManager のバインディング
        // リクエストスコープ: 各リクエストごとに新しいインスタンスを生成
        // 配送待ちコンテンツはリクエスト内でのみ保持される
        $this->app->bind(
            DeliveryManagerInterface::class,
            DeliveryManager::class
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
