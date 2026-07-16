<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Auth\RefreshTokenResponse;
use NexusAuth\Services\TokenService;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;

/**
 * RefreshTokenUseCase
 *
 * トークンリフレッシュのユースケース
 * リフレッシュトークンからアクセストークンを再発行
 */
class RefreshTokenUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly PlayerAuthService $playerAuthService,
        private readonly PlayerRepositoryInterface $playerRepository,
        private readonly DeviceRepositoryInterface $deviceRepository,
    ) {
    }

    /**
     * トークンリフレッシュ処理を実行
     *
     * @param string $refreshToken リフレッシュトークン
     * @return RefreshTokenResponse
     * @throws \Exception|\Throwable
     */
    public function handle(string $refreshToken): RefreshTokenResponse
    {
        // トランザクション開始
        return $this->executeWithTransaction(function () use ($refreshToken) {
            // リフレッシュトークンを検証（NexusAuth\Services\TokenService使用）
            $oldToken = $this->tokenService->validateRefreshToken($refreshToken);
            
            if ($oldToken === null) {
                throw new GameException(
                    GameErrorCode::INVALID_TOKEN,
                    'Invalid or expired refresh token'
                );
            }

            // プレイヤーとデバイスを取得
            $player = $this->playerRepository->selectById($oldToken->getPlayerId());
            
            // SysPlayerTokenからdevice_idを取得
            /** @var \App\Models\Sys\SysPlayerToken $oldToken */
            $deviceId = $oldToken->getSysPlayerDeviceId();
            $device = \App\Models\Sys\SysPlayerDevice::find($deviceId);

            if ($player === null || $device === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    'Player or device not found'
                );
            }

            // トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
            [$dtoToken, $newToken] = $this->tokenService->rotateToken(
                $oldToken,
                $player,
                $device,
                fn($playerId, $deviceId, $tokenHash, $expiresAt) => \App\Models\Sys\SysPlayerToken::create([
                    'sys_player_id' => $playerId,
                    'sys_player_device_id' => $deviceId,
                    'refresh_token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ])
            );

            // 最終ログイン日時を更新
            $this->playerAuthService->updateLastLogin($device->getUuid());

            return new RefreshTokenResponse(
                dtoToken: $dtoToken,
            );
        });
    }
}
