<?php

namespace Nexus\Core;

use Illuminate\Support\ServiceProvider;

/**
 * CoreServiceProvider
 * 
 * Nexus Coreパッケージのサービスプロバイダー
 * 
 * 提供機能:
 * - Models: Eloquent Model基底クラス（_BaseModel, _BaseTrx, _BaseSys, _BaseMst, _BaseLog）
 * - Repositories: Repository基底クラス
 * - Support: CustomCollection等
 * - Utilities: ClockUtility, RedisUtility
 * - ValueObjects: ErrorResponse等
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 必要に応じて追加
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 必要に応じて追加
    }
}
