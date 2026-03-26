<?php

namespace App\Providers;

use App\Utilities\QueryTrxManager;
use App\Utilities\QueryLogManager;
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
        $this->app->bind(
            AccountRepositoryInterface::class,
            AccountRepository::class
        );
        $this->app->bind(
            CharacterRepositoryInterface::class,
            CharacterRepository::class
        );
        $this->app->bind(
            ItemRepositoryInterface::class,
            ItemRepository::class
        );

        // Unit of Work パターン用のQueryManagerをシングルトンとして登録
        $this->app->singleton(QueryTrxManager::class);
        $this->app->singleton('query.manager', QueryTrxManager::class);
        $this->app->singleton(QueryLogManager::class);
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
