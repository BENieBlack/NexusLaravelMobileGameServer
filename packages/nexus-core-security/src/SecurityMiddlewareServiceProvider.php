<?php

namespace NexusSecurity;

use Illuminate\Support\ServiceProvider;

class SecurityMiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/Config/security.php', 'security'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 設定ファイルを公開
        $this->publishes([
            __DIR__.'/Config/security.php' => config_path('security.php'),
        ], 'laravel-security-middleware-config');
    }
}
