<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Traits\BuildsSysPlayerToken;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use Nexus\Core\Repositories\PlayerDeviceRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;

/**
 * RefreshTokenUseCase
 *
 * トークンリフレッシュのユースケース
 * リフレッシュトークンからアクセストークンを再発行
 */
class RefreshTokenUseCase extends _BaseUseCase
{
    use BuildsSysPlayerToken;

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly PlayerAuthService $playerAuthService,
        private readonly SysPlayerRepository $playerRepository,
        private readonly PlayerDeviceRepositoryInterface $deviceRepository,
    ) {}

    /**
     * トークンリフレッシュ処理を実行
     *
     * @param  string  $refreshToken  リフレッシュトークン
     *
     * @throws \Exception|\Throwable
     */
    public function exec(string $refreshToken): RefreshTokenResponse
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
            /** @var SysPlayerToken $oldToken */
            $deviceId = $oldToken->getSysPlayerDeviceId();
            $device = SysPlayerDevice::find($deviceId);

            if ($player === null || $device === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    'Player or device not found'
                );
            }

            // トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
            [$token, $newToken] = $this->tokenService->rotateToken(
                $oldToken,
                $player,
                $device,
                fn ($playerId, $deviceId, $tokenHash, $expiresAt) => $this->newSysPlayerToken(
                    $playerId,
                    $deviceId,
                    $tokenHash,
                    $expiresAt
                )
            );

            // 最終ログイン日時を更新
            $this->playerAuthService->updateLastLogin($device->getUuid());

            return new RefreshTokenResponse(
                token: $token,
            );
        });
    }
}
