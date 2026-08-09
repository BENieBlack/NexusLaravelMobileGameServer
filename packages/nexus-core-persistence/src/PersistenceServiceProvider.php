<?php

namespace NexusPersistence;

use Illuminate\Support\ServiceProvider;

/**
 * PersistenceServiceProvider
 * 
 * Laravel Persistence Core パッケージのサービスプロバイダー
 */
class PersistenceServiceProvider extends ServiceProvider
{
    /**
     * サービスの登録
     *
     * @return void
     */
    public function register(): void
    {
        // このパッケージは基底クラスのみを提供するため、特別な登録は不要
    }

    /**
     * サービスの起動
     *
     * @return void
     */
    public function boot(): void
    {
        // Load migrations from specific subdirectories
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/sys');
    }
}
