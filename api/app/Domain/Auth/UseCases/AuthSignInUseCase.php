<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\SignInResponse;
use App\Models\Sys\SysPlayerToken;
use Exception;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use Throwable;

/**
 * AuthSignInUseCase
 *
 * サインインのユースケース
 * 既存デバイスIDでのログイン処理
 * 新しいアクセストークンとリフレッシュトークンを発行
 */
class AuthSignInUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly PlayerAuthService $playerAuthService,
        private readonly TokenService $tokenService,
        private readonly DeviceRepositoryInterface $deviceRepository,
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly TokenRepositoryInterface $tokenRepository,
    ) {}

    /**
     * サインイン処理を実行
     *
     * 既存デバイスIDで新しいトークンを発行
     *
     * @param  string  $deviceId  デバイスID
     * @param  array  $deviceInfo  デバイス情報（現在未使用だが将来的な拡張のため保持）
     *
     * @throws GameException デバイスIDが存在しない場合
     * @throws Exception|Throwable
     */
    public function exec(string $deviceId, array $deviceInfo): SignInResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($deviceId) {
            // デバイスを取得
            $sysPlayerDevice = $this->deviceRepository->selectByDeviceId($deviceId);

            if ($sysPlayerDevice === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    "Device ID not found: {$deviceId}. Please use sign_up endpoint."
                );
            }

            // プレイヤー情報を取得
            $sysPlayer = $this->playerRepository->selectById($sysPlayerDevice->getPlayerId());

            if ($sysPlayer === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    "Player not found for device: {$deviceId}"
                );
            }

            // 古いトークンを無効化（PlayerAuthService使用）
            $this->tokenRepository->deleteByPlayerId($sysPlayer->getId());

            // Token DTO生成（NexusAuth\Services\TokenService使用）
            [$tokenDto, $sysPlayerToken] = $this->tokenService->generateToken(
                $sysPlayer,
                $sysPlayerDevice,
                fn ($playerId, $deviceId, $tokenHash, $expiresAt) => SysPlayerToken::create([
                    'sys_player_id' => $playerId,
                    'sys_player_device_id' => $deviceId,
                    'refresh_token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ])
            );

            // 最終ログイン日時を更新（PlayerAuthService使用）
            $this->playerAuthService->updateLastLogin($deviceId);

            return new SignInResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                tokenDto: $tokenDto,
            );
        });
    }
}
