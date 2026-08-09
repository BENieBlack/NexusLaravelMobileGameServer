<?php

namespace NexusPitr;

use Illuminate\Support\ServiceProvider;
use NexusPitr\Logger\TrxChangeLogger;
use NexusPitr\Commands\PitrMigrateCommand;
use NexusPitr\Commands\PitrRollbackCommand;
use NexusPitr\Commands\TrxMigrateCommand;
use NexusPitr\Commands\TrxRollbackCommand;

class NexusPitrServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // TrxChangeLoggerをシングルトンとして登録
        $this->app->singleton(TrxChangeLogger::class, function ($app) {
            return new TrxChangeLogger();
        });

        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/nexus-pitr.php', 'nexus-pitr'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // マイグレーションをロード
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 設定ファイルを公開
        $this->publishes([
            __DIR__.'/../config/nexus-pitr.php' => config_path('nexus-pitr.php'),
        ], 'nexus-pitr-config');

        // Artisanコマンドを登録
        if ($this->app->runningInConsole()) {
            $this->commands([
                PitrMigrateCommand::class,
                PitrRollbackCommand::class,
                TrxMigrateCommand::class,
                TrxRollbackCommand::class,
            ]);
        }
    }
}
