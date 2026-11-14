<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\SignUpResponse;
use App\Domain\Auth\Traits\BuildsSysPlayerToken;
use App\Domain\DoesNotReturnUpdatedResources;
use App\Domain\Player\Contracts\InitialGrantInterface;
use App\Domain\Sharding\Contracts\ShardAssignerInterface;
use App\Exceptions\BusinessLogicException;
use App\Models\Sys\SysPlayer;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusTidb\Support\TidbMode;
use Throwable;

/**
 * SignUpUseCase
 *
 * サインアップのユースケース
 * 新規プレイヤー作成とトークン発行を行う
 * 既存デバイスIDの場合はエラーを返す
 */
#[DoesNotReturnUpdatedResources('sign_up の後に、クライアントが auth/login で現在値を全て取得する')]
class SignUpUseCase extends _BaseUseCase
{
    use BuildsSysPlayerToken;

    public function __construct(
        private readonly PlayerAuthService $playerAuthService,
        private readonly TokenService $tokenService,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
        private readonly ShardAssignerInterface $shardAssigner,
        private readonly InitialGrantInterface $initialGrant,
    ) {}

    /**
     * サインアップ処理を実行
     *
     * 新規デバイスIDの場合のみプレイヤーを作成
     * 既存デバイスIDの場合はエラーを返す（sign_inを使用すべき）
     *
     * @param  string  $deviceId  デバイスID
     * @param  array<string, mixed>  $deviceInfo
     *
     * @throws BusinessLogicException|Throwable 既存デバイスIDの場合
     */
    public function handle(string $deviceId, array $deviceInfo): SignUpResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            // 既存デバイスチェック
            $existingDevice = $this->sysPlayerDeviceRepository->selectByDeviceId($deviceId);

            if ($existingDevice !== null) {
                // 既存デバイスの場合はエラー（sign_inを使用すべき）
                throw BusinessLogicException::deviceAlreadyExists($deviceId);
            }

            // 新規プレイヤー作成（PlayerAuthService使用）。
            // パッケージ側の戻り値の型は PlayerModelInterface だが、実体はこのアプリの SysPlayer。
            // レスポンスとトークン生成にはモデルが要るので、型を確かめて使う（再取得はしない）
            $sysPlayer = $this->playerAuthService->createPlayer($deviceId, $deviceInfo);

            if (! $sysPlayer instanceof SysPlayer) {
                throw new \LogicException('createPlayer() が SysPlayer を返しませんでした: '.$sysPlayer::class);
            }

            // TiDBではDB側の分散に任せるため、アプリケーション側の割り当ては作らない。
            if (! TidbMode::isEnabled()) {
                $this->shardAssigner->assign($sysPlayer->getId());
            }

            // 初期データ（trx_diamond・trx_wallet・trx_item・trx_stamina）を付与する。
            // sign_up は未認証で ApiSession にプレイヤーIDが無く、trx の書き込み先シャードを
            // 解決できないので、付与の前に新規プレイヤーを載せる。
            // assign() は sys へ即時 INSERT 済みなので、同じトランザクションの sys 接続から読める。
            // 付与は直接呼びで、失敗の例外は握りつぶされず executeWithTransaction まで届き、
            // 全接続がロールバックされる（sys_player も残らない）。
            // 10004（既存デバイス）は上で throw 済みなので、再送で二重付与はしない
            ApiSession::setSysPlayerId($sysPlayer->getId());
            $this->initialGrant->grant($sysPlayer->getId(), ApiSession::getPlatform());

            // デバイスを作成（アプリケーション層で直接作成）
            $sysPlayerDevice = $this->sysPlayerDeviceRepository->insertDevice(
                $sysPlayer->getId(),
                $deviceId,
                $deviceInfo
            );

            // Token DTO生成（NexusAuth\Services\TokenService使用）
            [$token, $sysPlayerToken] = $this->tokenService->generateToken(
                $sysPlayer,
                $sysPlayerDevice,
                fn ($playerId, $deviceId, $tokenHash, $expiresAt) => $this->newSysPlayerToken(
                    $playerId,
                    $deviceId,
                    $tokenHash,
                    $expiresAt
                )
            );

            // レスポンスにトークンIDを含めるため、採番を確定させる
            $this->flushQueue();

            return new SignUpResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                token: $token,
            );
        });
    }
}
