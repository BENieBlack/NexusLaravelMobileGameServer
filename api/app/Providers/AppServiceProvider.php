<?php

namespace App\Providers;

use App\Domain\Album\Handlers\AlbumRecordingDeliveryHandler;
use App\Domain\Item\Services\ItemGranterAdapter;
use App\Domain\Item\Support\WalletItemMigrator;
use App\Domain\Login\Services\ComeBackLoginBonusService;
use App\Domain\Login\Services\LoginBonusService;
use App\Domain\Login\Services\VipLoginBonusService;
use App\Domain\Player\Services\ExperienceGranterAdapter;
use App\Domain\Player\Services\PlayerLevelServiceAdapter;
use App\Domain\Player\Services\PlayerLevelUpStaminaHandler;
use App\Domain\Stamina\Services\StaminaGranterAdapter;
use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use App\Repositories\Log\LogVipPointRepository;
use App\Repositories\Mst\AlbumCatalogRepositoryAdapter;
use App\Repositories\Mst\LoginBonusRepositoryAdapter;
use App\Repositories\Mst\MstGachaPrizeRepository;
use App\Repositories\Mst\MstGachaRarityRateRepository;
use App\Repositories\Mst\MstGachaStepBonusContentRepository;
use App\Repositories\Mst\MstGachaStepBonusRepository;
use App\Repositories\Mst\MstGachaStepRepository;
use App\Repositories\Mst\MstVipLevelRepository;
use App\Repositories\Mst\MstVipLevelRewardRepository;
use App\Repositories\Mst\MstVipLoginBonusRepository;
use App\Repositories\Mst\PlayerLevelRepositoryAdapter;
use App\Repositories\Mst\RewardTrackMasterRepository;
use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use App\Repositories\Sys\DeployRepositoryAdapter;
use App\Repositories\Sys\FriendApplyRepositoryAdapter;
use App\Repositories\Sys\GuildApplyRepositoryAdapter;
use App\Repositories\Sys\GuildMemberRepositoryAdapter;
use App\Repositories\Sys\GuildRepositoryAdapter;
use App\Repositories\Sys\PlayerRepositoryAdapter;
use App\Repositories\Sys\SysChatMessageRepository;
use App\Repositories\Sys\SysChatRoomMemberRepository;
use App\Repositories\Sys\SysChatRoomRepository;
use App\Repositories\Sys\SysMaintenanceRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use App\Repositories\Trx\AlbumEntryRepositoryAdapter;
use App\Repositories\Trx\DiamondRepositoryAdapter;
use App\Repositories\Trx\EquipmentRepositoryAdapter;
use App\Repositories\Trx\GachaProgressRepositoryAdapter;
use App\Repositories\Trx\ItemRepositoryAdapter;
use App\Repositories\Trx\LoginBonusHistoryRepositoryAdapter;
use App\Repositories\Trx\MailboxRepositoryAdapter;
use App\Repositories\Trx\StaminaRepositoryAdapter;
use App\Repositories\Trx\TrxNotificationRepository;
use App\Repositories\Trx\TrxRewardTrackLineRepository;
use App\Repositories\Trx\TrxRewardTrackMilestoneRepository;
use App\Repositories\Trx\TrxRewardTrackRepository;
use App\Repositories\Trx\TrxVipLoginBonusHistoryRepository;
use App\Repositories\Trx\UnitRepositoryAdapter;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use App\Repositories\Trx\WalletBalanceRepositoryAdapter;
use App\Repositories\Trx\WalletRepositoryAdapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nexus\Core\Repositories\PlayerDeviceRepositoryInterface;
use Nexus\Core\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusAlbum\Repositories\AlbumCatalogRepositoryInterface;
use NexusAlbum\Repositories\AlbumEntryRepositoryInterface;
use NexusAlbum\Services\AlbumService;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusAuth\Services\TokenValidator;
use NexusChat\Contracts\ChatMessageRepositoryInterface;
use NexusChat\Contracts\ChatRoomMemberRepositoryInterface;
use NexusChat\Contracts\ChatRoomRepositoryInterface;
use NexusFriend\Repositories\FriendApplyRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;
use NexusGuild\Repositories\GuildRepositoryInterface;
use NexusLevel\Contracts\PlayerLevelUpHandlerInterface;
use NexusLevel\Repositories\PlayerLevelRepositoryInterface;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\LoginBonusOrchestrator;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusNotification\Contracts\NotificationRepositoryInterface;
use NexusResource\Contracts\DiamondRepositoryInterface;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResourceDelivery\Contracts\EquipmentRepositoryInterface;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;
use NexusResourceDelivery\Contracts\ItemGranterInterface;
use NexusResourceDelivery\Contracts\StaminaGranterInterface;
use NexusResourceDelivery\Contracts\UnitRepositoryInterface;
use NexusResourceDelivery\Handlers\CurrencyDeliveryHandler;
use NexusResourceDelivery\Handlers\DiamondDeliveryHandler;
use NexusResourceDelivery\Handlers\EquipmentDeliveryHandler;
use NexusResourceDelivery\Handlers\ExperienceDeliveryHandler;
use NexusResourceDelivery\Handlers\ItemDeliveryHandler;
use NexusResourceDelivery\Handlers\NaturalResourceDeliveryHandler;
use NexusResourceDelivery\Handlers\PointsDeliveryHandler;
use NexusResourceDelivery\Handlers\ResourceDeliveryHandlerInterface;
use NexusResourceDelivery\Handlers\StaminaDeliveryHandler;
use NexusResourceDelivery\Handlers\UnitDeliveryHandler;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackLineRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackMilestoneRepositoryInterface;
use NexusRewardTrack\Repositories\RewardTrackRepositoryInterface;
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
use NexusWallet\Repositories\WalletBalanceRepositoryInterface;
use NexusWallet\Repositories\WalletRepositoryInterface;

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
        $this->app->bind(PlayerDeviceRepositoryInterface::class, SysPlayerDeviceRepository::class);
        $this->app->bind(TokenRepositoryInterface::class, SysPlayerTokenRepository::class);

        // TokenService
        //
        // singletonではなくscopedを使う。TokenServiceはSys配下のRepositoryを
        // コンストラクタで受け取って持ち続けるため、singletonにすると
        // Octaneやキューワーカーでリクエストを跨いでインスタンスが残り、
        // 別プレイヤーのキャッシュを持ち越してしまう
        $this->app->scoped(TokenService::class, function ($app) {
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
        $this->app->bind(LoginBonusRepositoryInterface::class, LoginBonusRepositoryAdapter::class);
        $this->app->bind(LoginBonusHistoryRepositoryInterface::class, LoginBonusHistoryRepositoryAdapter::class);

        // VIPログインボーナス用Repository
        $this->app->bind(VipLoginBonusRepositoryInterface::class, MstVipLoginBonusRepository::class);
        $this->app->bind(VipLoginBonusHistoryRepositoryInterface::class, TrxVipLoginBonusHistoryRepository::class);

        // LoginBonusOrchestrator (singleton)
        $this->app->singleton(LoginBonusOrchestrator::class);

        // ==========================================
        // NexusVersion Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(DeployRepositoryInterface::class, DeployRepositoryAdapter::class);
        $this->app->bind(MaintenanceRepositoryInterface::class, SysMaintenanceRepository::class);

        // ==========================================
        // NexusStamina Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(StaminaRepositoryInterface::class, StaminaRepositoryAdapter::class);
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
        $this->app->bind(GachaProgressRepositoryInterface::class, GachaProgressRepositoryAdapter::class);

        // ==========================================
        // NexusMailbox Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(MailboxRepositoryInterface::class, MailboxRepositoryAdapter::class);

        // ==========================================
        // NexusPlayer Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(PlayerRepoInterface::class, PlayerRepositoryAdapter::class);
        $this->app->bind(PlayerLevelRepositoryInterface::class, PlayerLevelRepositoryAdapter::class);

        // レベルアップ時のゲーム固有処理（スタミナ全回復）
        $this->app->bind(PlayerLevelUpHandlerInterface::class, PlayerLevelUpStaminaHandler::class);

        // ==========================================
        // NexusVip Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(VipLevelRepositoryInterface::class, MstVipLevelRepository::class);
        $this->app->bind(VipLevelRewardRepositoryInterface::class, MstVipLevelRewardRepository::class);
        $this->app->bind(VipPointLogRepositoryInterface::class, LogVipPointRepository::class);
        $this->app->bind(PlayerVipRepositoryInterface::class, PlayerRepositoryAdapter::class);

        // ==========================================
        // NexusGuild Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(GuildRepositoryInterface::class, GuildRepositoryAdapter::class);
        $this->app->bind(GuildMemberRepositoryInterface::class, GuildMemberRepositoryAdapter::class);
        $this->app->bind(GuildApplyRepositoryInterface::class, GuildApplyRepositoryAdapter::class);

        // ==========================================
        // NexusFriend Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(FriendApplyRepositoryInterface::class, FriendApplyRepositoryAdapter::class);

        // ゲーム内通知
        $this->app->bind(NotificationRepositoryInterface::class, TrxNotificationRepository::class);

        // チャット（フレンドDM・ギルド・グループ）
        $this->app->bind(ChatRoomRepositoryInterface::class, SysChatRoomRepository::class);
        $this->app->bind(ChatMessageRepositoryInterface::class, SysChatMessageRepository::class);
        $this->app->bind(ChatRoomMemberRepositoryInterface::class, SysChatRoomMemberRepository::class);

        // アルバム（収集記録）
        $this->app->bind(AlbumEntryRepositoryInterface::class, AlbumEntryRepositoryAdapter::class);
        $this->app->bind(AlbumCatalogRepositoryInterface::class, AlbumCatalogRepositoryAdapter::class);

        // ==========================================
        // NexusResource Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(ItemRepositoryInterface::class, ItemRepositoryAdapter::class);

        // ==========================================
        // NexusBilling Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(DiamondRepositoryInterface::class, DiamondRepositoryAdapter::class);

        // ==========================================
        // NexusWallet (NexusWallet) Package Bindings
        // ==========================================

        // Repository interfaces
        $this->app->bind(WalletRepositoryInterface::class, WalletRepositoryAdapter::class);
        $this->app->bind(WalletBalanceRepositoryInterface::class, WalletBalanceRepositoryAdapter::class);

        // ==========================================
        // ResourceDelivery Package Bindings
        // ==========================================

        // Repository interfaces
        // ユニット/装備はパッケージ側にDTOを持たないため、Adapterが直接Modelを組み立てる
        $this->app->bind(UnitRepositoryInterface::class, UnitRepositoryAdapter::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EquipmentRepositoryAdapter::class);
        $this->app->bind(ItemGranterInterface::class, ItemGranterAdapter::class);

        // 同じリクエスト内で二重に移さないよう、移行済みの記憶を共有する
        $this->app->scoped(WalletItemMigrator::class);

        $this->app->bind(StaminaGranterInterface::class, StaminaGranterAdapter::class);
        $this->app->bind(ExperienceGranterInterface::class, ExperienceGranterAdapter::class);

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

        // ==========================================
        // NexusRewardTrack Package Bindings
        // ==========================================
        $this->app->singleton(RewardTrackMasterRepositoryInterface::class, RewardTrackMasterRepository::class);
        $this->app->scoped(RewardTrackRepositoryInterface::class, TrxRewardTrackRepository::class);
        $this->app->scoped(RewardTrackLineRepositoryInterface::class, TrxRewardTrackLineRepository::class);
        $this->app->scoped(RewardTrackMilestoneRepositoryInterface::class, TrxRewardTrackMilestoneRepository::class);

        $this->registerScopedRepositories();
    }

    /**
     * Repositoryをリクエストスコープで共有する
     *
     * Trx/Log/SysのRepositoryは取得したモデルをインスタンス内にキャッシュする。
     * 注入のたびに別インスタンスだと同じSELECTがUseCaseごとに走り、
     * 書き込みキューもインスタンスごとに分かれてしまうため、
     * リクエスト（キューならジョブ）単位で1つを共有する。
     *
     * singletonではなくscopedを使う。singletonはOctaneやキューワーカーで
     * リクエストを跨いでインスタンスが残り、別プレイヤーのキャッシュを
     * 持ち越してしまう。
     *
     * Mstは_BaseMstRepositoryが静的にキャッシュするため対象外。
     */
    private function registerScopedRepositories(): void
    {
        foreach (['Trx', 'Log', 'Sys'] as $group) {
            foreach (glob(app_path("Repositories/{$group}/*.php")) ?: [] as $file) {
                $class = "App\\Repositories\\{$group}\\".basename($file, '.php');

                // 抽象基底クラス（_Base*）とインターフェースは解決対象にしない
                if (str_starts_with(basename($file), '_') || str_ends_with($file, 'Interface.php')) {
                    continue;
                }

                $this->app->scoped($class);
            }
        }
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
            database_path('migrations/sys'),
            database_path('migrations/adm'),
        ]);

        // ==========================================
        // ResourceDeliveryService Handlers Registration
        // ==========================================

        // ResourceDeliveryServiceにHandlerを登録
        $this->app->afterResolving(ResourceDeliveryService::class, function (ResourceDeliveryService $service, $app) {
            // アイテム・ユニット・装備はアルバムの記録対象。
            // findHandler()は最初に一致した1つしか返さないため、
            // 並べて登録するのではなく本来のHandlerを包む
            $recordToAlbum = fn (ResourceDeliveryHandlerInterface $handler) => new AlbumRecordingDeliveryHandler(
                $handler,
                $app->make(AlbumService::class),
            );

            $service->registerHandler($recordToAlbum($app->make(ItemDeliveryHandler::class)));
            $service->registerHandler($recordToAlbum($app->make(UnitDeliveryHandler::class)));
            $service->registerHandler($recordToAlbum($app->make(EquipmentDeliveryHandler::class)));
            $service->registerHandler($app->make(DiamondDeliveryHandler::class));
            $service->registerHandler($app->make(CurrencyDeliveryHandler::class));
            $service->registerHandler($app->make(NaturalResourceDeliveryHandler::class));
            $service->registerHandler($app->make(PointsDeliveryHandler::class));
            $service->registerHandler($app->make(StaminaDeliveryHandler::class));
            $service->registerHandler($app->make(ExperienceDeliveryHandler::class));
        });

        // ==========================================
        // Slack Error Notification - Ignore Error Codes
        // ==========================================

        // Slack通知をしないエラーコードを設定する。
        // ユーザー起因の想定内エラー（認証失敗・入力ミス等）を除外することで
        // 通知のノイズを減らす。
        config(['security.slack_ignore_error_codes' => [
            // 認証関連（ユーザー起因の想定内エラー）
            GameErrorCode::AUTHENTICATION_FAILED,     // 10001
            GameErrorCode::PLAYER_NOT_FOUND,          // 10002
            GameErrorCode::INVALID_TOKEN,             // 10003

            // バリデーション・パラメータエラー
            GameErrorCode::INVALID_PARAMETER,         // 99001
            GameErrorCode::VALIDATION_FAILED,         // 99002
        ]]);

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
