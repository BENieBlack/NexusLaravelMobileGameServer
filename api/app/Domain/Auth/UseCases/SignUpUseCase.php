<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Traits\BuildsSysPlayerToken;
use App\Exceptions\BusinessLogicException;
use App\Http\Responses\Auth\SignUpResponse;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusPlayer\Repositories\PlayerDeviceRepositoryInterface;
use Throwable;

/**
 * SignUpUseCase
 *
 * サインアップのユースケース
 * 新規プレイヤー作成とトークン発行を行う
 * 既存デバイスIDの場合はエラーを返す
 */
class SignUpUseCase extends _BaseUseCase
{
    use BuildsSysPlayerToken;

    public function __construct(
        private readonly PlayerAuthService $playerAuthService,
        private readonly TokenService $tokenService,
        private readonly PlayerDeviceRepositoryInterface $deviceRepository,
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {}

    /**
     * サインアップ処理を実行
     *
     * 新規デバイスIDの場合のみプレイヤーを作成
     * 既存デバイスIDの場合はエラーを返す（sign_inを使用すべき）
     *
     * @param  string  $deviceId  デバイスID
     * @param  array  $deviceInfo  デバイス情報
     *
     * @throws BusinessLogicException|Throwable 既存デバイスIDの場合
     */
    public function exec(string $deviceId, array $deviceInfo): SignUpResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            // 既存デバイスチェック
            $existingDevice = $this->deviceRepository->selectByDeviceId($deviceId);

            if ($existingDevice !== null) {
                // 既存デバイスの場合はエラー（sign_inを使用すべき）
                throw BusinessLogicException::deviceAlreadyExists($deviceId);
            }

            // 新規プレイヤー作成（PlayerAuthService使用）
            $player = $this->playerAuthService->createPlayer($deviceId, $deviceInfo);

            // レスポンスとトークン生成にはModelが要る。
            // 直前に採番済みでリポジトリのメモリキャッシュに載っているため追加クエリは発生しない
            $sysPlayer = $this->sysPlayerRepository->selectById($player->getId());

            // デバイスを作成（アプリケーション層で直接作成）
            $sysPlayerDevice = $this->sysPlayerDeviceRepository->insertDevice(
                $player->getId(),
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

            // レスポンスを返却（新規作成なので201）
            return new SignUpResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                token: $token,
            );
        });
    }
}
