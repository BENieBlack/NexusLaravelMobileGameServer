<?php

namespace App\Providers;

use App\Domain\Auth\Services\TokenValidator;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use App\Repositories\Sys\SysDeployRepository;
use App\Repositories\Sys\SysMaintenanceRepository;
use App\Repositories\Mst\EloquentLoginBonusRepository;
use App\Repositories\Mst\EloquentGachaMasterRepository;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Trx\EloquentLoginBonusHistoryRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Repositories\Trx\TrxGachaRepository;
use App\Repositories\Trx\TrxMailboxRepository;
use App\Domain\Player\Services\PlayerLevelServiceAdapter;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\TokenService;
use NexusAuth\Services\PlayerAuthService;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusVersion\Repositories\DeployRepositoryInterface;
use NexusVersion\Repositories\MaintenanceRepositoryInterface;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Services\PlayerLevelServiceInterface;
use NexusGacha\Repositories\GachaMasterRepositoryInterface;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusPlayer\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;
use NexusPlayer\Repositories\PlayerLevelRepositoryInterface;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusResourceDelivery\Handlers\ItemDeliveryHandler;
use NexusResourceDelivery\Handlers\UnitDeliveryHandler;
use NexusResourceDelivery\Handlers\EquipmentDeliveryHandler;
use NexusResourceDelivery\Handlers\DiamondDeliveryHandler;
use NexusResourceDelivery\Handlers\CurrencyDeliveryHandler;
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
        // ==========================================
        // NexusAuth Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(PlayerRepositoryInterface::class, SysPlayerRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, SysPlayerDeviceRepository::class);
        $this->app->bind(TokenRepositoryInterface::class, SysPlayerTokenRepository::class);
        
        // TokenService (singleton)
        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(
                tokenRepository: $app->make(TokenRepositoryInterface::class),
                appKey: config('app.key'),
                accessTokenExpiration: 3600, // 1時間
                refreshTokenExpirationDays: 30, // 30日
            );
        });
        
        // PlayerAuthService
        $this->app->bind(PlayerAuthService::class);
        
        // ==========================================
        // NexusLogin Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(LoginBonusRepositoryInterface::class, EloquentLoginBonusRepository::class);
        $this->app->bind(LoginBonusHistoryRepositoryInterface::class, EloquentLoginBonusHistoryRepository::class);
        
        // ==========================================
        // NexusVersion Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(DeployRepositoryInterface::class, SysDeployRepository::class);
        $this->app->bind(MaintenanceRepositoryInterface::class, SysMaintenanceRepository::class);
        
        // ==========================================
        // NexusStamina Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(StaminaRepositoryInterface::class, TrxStaminaRepository::class);
        $this->app->bind(PlayerLevelServiceInterface::class, PlayerLevelServiceAdapter::class);
        
        // ==========================================
        // NexusGacha Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(GachaMasterRepositoryInterface::class, EloquentGachaMasterRepository::class);
        $this->app->bind(GachaProgressRepositoryInterface::class, TrxGachaRepository::class);
        
        // ==========================================
        // NexusMailbox Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(MailboxRepositoryInterface::class, TrxMailboxRepository::class);
        
        // ==========================================
        // NexusPlayer Package Bindings
        // ==========================================
        
        // Repository interfaces
        $this->app->bind(PlayerRepoInterface::class, SysPlayerRepository::class);
        $this->app->bind(PlayerDeviceRepositoryInterface::class, SysPlayerDeviceRepository::class);
        $this->app->bind(PlayerLevelRepositoryInterface::class, MstPlayerLevelRepository::class);
        
        // ==========================================
        // ResourceDelivery Package Bindings
        // ==========================================
        
        // ResourceDeliveryManager のバインディング
        // リクエストスコープ: 各リクエストごとに新しいインスタンスを生成
        // 配送待ちコンテンツはリクエスト内でのみ保持される
        $this->app->bind(
            ResourceDeliveryManagerInterface::class,
            ResourceDeliveryManager::class
        );

        // ==========================================
        // Unit of Work Pattern Bindings
        // ==========================================
        
        // Unit of Work パターン用のQueryManagerをシングルトンとして登録
        $this->app->singleton(QueryManager::class);
        $this->app->singleton('query.manager', QueryManager::class);
        
        // Unit of Work パッケージのQueryManagerInterfaceもバインド
        $this->app->singleton(UnitOfWorkQueryManagerInterface::class, QueryManager::class);

        // ==========================================
        // Security Package Bindings
        // ==========================================
        
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
        
        // ==========================================
        // ResourceDeliveryService Handlers Registration
        // ==========================================
        
        // ResourceDeliveryServiceにHandlerを登録
        $this->app->afterResolving(ResourceDeliveryService::class, function (ResourceDeliveryService $service, $app) {
            $service->registerHandler($app->make(ItemDeliveryHandler::class));
            $service->registerHandler($app->make(UnitDeliveryHandler::class));
            $service->registerHandler($app->make(EquipmentDeliveryHandler::class));
            $service->registerHandler($app->make(DiamondDeliveryHandler::class));
            $service->registerHandler($app->make(CurrencyDeliveryHandler::class));
        });
    }
}
