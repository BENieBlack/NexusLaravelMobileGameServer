<?php

namespace App\Providers;

use App\Domain\Auth\Services\TokenValidator;
use App\Domain\Login\Services\ComeBackLoginBonusService;
use App\Domain\Login\Services\LoginBonusService;
use App\Domain\Login\Services\VipLoginBonusService;
use App\Domain\Player\Services\PlayerLevelServiceAdapter;
use App\Persistence\ApiSession;
use App\Repositories\Log\LogVipPointRepository;
use App\Repositories\Mst\MstGachaPrizeRepository;
use App\Repositories\Mst\MstGachaRarityRateRepository;
use App\Repositories\Mst\MstGachaStepBonusContentRepository;
use App\Repositories\Mst\MstGachaStepBonusRepository;
use App\Repositories\Mst\MstGachaStepRepository;
use App\Repositories\Mst\MstLoginBonusRepository;
use App\Repositories\Mst\MstPlayerLevelRepository;
use App\Repositories\Mst\MstVipLevelRepository;
use App\Repositories\Mst\MstVipLevelRewardRepository;
use App\Repositories\Mst\MstVipLoginBonusRepository;
use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use App\Repositories\Sys\SysDeployRepository;
use App\Repositories\Sys\SysGuildApplyRepository;
use App\Repositories\Sys\SysGuildMemberRepository;
use App\Repositories\Sys\SysGuildRepository;
use App\Repositories\Sys\SysMaintenanceRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use App\Repositories\Trx\DiamondRepositoryImpl;
use App\Repositories\Trx\ItemRepositoryImpl;
use App\Repositories\Trx\TrxGachaRepository;
use App\Repositories\Trx\TrxLoginBonusHistoryRepository;
use App\Repositories\Trx\TrxMailboxRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Repositories\Trx\TrxVipLoginBonusHistoryRepository;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use App\Repositories\Trx\WalletBalanceRepository;
use App\Repositories\Trx\WalletRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusBilling\Contracts\DiamondRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;
use NexusGuild\Repositories\GuildRepositoryInterface;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\LoginBonusOrchestrator;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;
use NexusPlayer\Repositories\PlayerLevelRepositoryInterface;
use NexusPlayer\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResourceDelivery\Handlers\CurrencyDeliveryHandler;
use NexusResourceDelivery\Handlers\DiamondDeliveryHandler;
use NexusResourceDelivery\Handlers\EquipmentDeliveryHandler;
use NexusResourceDelivery\Handlers\ItemDeliveryHandler;
use NexusResourceDelivery\Handlers\UnitDeliveryHandler;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusSecurity\Contracts\PlayerSessionInterface;
use NexusSecurity\Contracts\TokenValidatorInterface;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Services\PlayerLevelServiceInterface;
use NexusUnitOfWork\Contracts\PlayerSessionResolverInterface;
use NexusVersion\Repositories\DeployRepositoryInterface;
use NexusVersion\Repositories\MaintenanceRepositoryInterface;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipLevelRepositoryInterface;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use LaravelWallet\Repositories\WalletRepositoryInterface;
use LaravelWallet\Repositories\WalletBalanceRepositoryInterface;

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
        $this->app->bind(LoginBonusRepositoryInterface::class, MstLoginBonusRepository::class);
        $this->app->bind(LoginBonusHistoryRepositoryInterface::class, TrxLoginBonusHistoryRepository::class);

        // VIPログインボーナス用Repository
        $this->app->bind(VipLoginBonusRepositoryInterface::class, MstVipLoginBonusRepository::class);
        $this->app->bind(VipLoginBonusHistoryRepositoryInterface::class, TrxVipLoginBonusHistoryRepository::class);

        // LoginBonusOrchestrator (singleton)
        $this->app->singleton(LoginBonusOrchestrator::class);

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
        $this->app->bind(GachaRarityRateRepositoryInterface::class, MstGachaRarityRateRepository::class);
        $this->app->bind(GachaPrizeRepositoryInterface::class, MstGachaPrizeRepository::class);
        $this->app->bind(GachaStepRepositoryInterface::class, MstGachaStepRepository::class);
        $this->app->bind(GachaStepBonusRepositoryInterface::class, MstGachaStepBonusRepository::class);
        $this->app->bind(GachaStepBonusContentRepositoryInterface::class, MstGachaStepBonusContentRepository::class);
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
        // NexusVip Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(VipLevelRepositoryInterface::class, MstVipLevelRepository::class);
        $this->app->bind(VipLevelRewardRepositoryInterface::class, MstVipLevelRewardRepository::class);
        $this->app->bind(VipPointLogRepositoryInterface::class, LogVipPointRepository::class);
        $this->app->bind(PlayerVipRepositoryInterface::class, SysPlayerRepository::class);

        // ==========================================
        // NexusGuild Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(GuildRepositoryInterface::class, SysGuildRepository::class);
        $this->app->bind(GuildMemberRepositoryInterface::class, SysGuildMemberRepository::class);
        $this->app->bind(GuildApplyRepositoryInterface::class, SysGuildApplyRepository::class);

        // ==========================================
        // NexusResource Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(ItemRepositoryInterface::class, ItemRepositoryImpl::class);

        // ==========================================
        // NexusBilling Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(DiamondRepositoryInterface::class, DiamondRepositoryImpl::class);

        // ==========================================
        // NexusWallet (LaravelWallet) Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(WalletRepositoryInterface::class, WalletRepository::class);
        $this->app->bind(WalletBalanceRepositoryInterface::class, WalletBalanceRepository::class);

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
        // Security Package Bindings
        // ==========================================

        // セキュリティミドルウェアパッケージ用のインターフェースバインディング
        $this->app->bind(TokenValidatorInterface::class, TokenValidator::class);

        // P2-5: ApiSessionをscopedバインドに変更（リクエストスコープ）
        // リクエストごとに新しいインスタンスを生成し、リクエスト内では同一インスタンスを共有
        $this->app->scoped(PlayerSessionInterface::class, ApiSession::class);

        // Unit of Work パッケージ用のPlayerSessionResolverInterfaceバインディング
        $this->app->scoped(PlayerSessionResolverInterface::class, ApiSession::class);

        // ApiSessionクラス自体もscopedバインドとして登録
        $this->app->scoped(ApiSession::class);
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

        // ==========================================
        // LoginBonusOrchestrator Strategies Registration
        // ==========================================

        // LoginBonusOrchestratorに戦略を登録
        $this->app->afterResolving(LoginBonusOrchestrator::class, function ($orchestrator, $app) {
            // 通常ログインボーナス戦略（優先度: 100）
            $orchestrator->registerStrategy(
                $app->make(LoginBonusService::class),
                100
            );

            // VIPログインボーナス戦略（優先度: 150）
            $orchestrator->registerStrategy(
                $app->make(VipLoginBonusService::class),
                150
            );

            // カムバックログインボーナス戦略（優先度: 200、最優先）
            $orchestrator->registerStrategy(
                $app->make(ComeBackLoginBonusService::class),
                200
            );
        });
    }
}
