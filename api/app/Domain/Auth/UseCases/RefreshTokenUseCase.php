<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\RefreshTokenResponse;
use App\Domain\Auth\Traits\BuildsSysPlayerToken;
use App\Domain\DoesNotReturnUpdatedResources;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;

/**
 * RefreshTokenUseCase
 *
 * トークンリフレッシュのユースケース
 * リフレッシュトークンからアクセストークンを再発行
 */
#[DoesNotReturnUpdatedResources('トークンの更新で、画面に出る状態は変わらない（更新するのはログイン日時など）')]
class RefreshTokenUseCase extends _BaseUseCase
{
    use BuildsSysPlayerToken;

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly PlayerAuthService $playerAuthService,
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {}

    /**
     * トークンリフレッシュ処理を実行
     *
     * @param  string  $refreshToken  リフレッシュトークン
     *
     * @throws \Exception|\Throwable
     */
    public function handle(string $refreshToken): RefreshTokenResponse
    {
        // トランザクション開始
        $token = $this->executeWithTransaction(function () use ($refreshToken) {
            // リフレッシュトークンを検証（NexusAuth\Services\TokenService使用）
            $oldToken = $this->tokenService->validateRefreshToken($refreshToken);

            if ($oldToken === null) {
                throw new GameException(
                    GameErrorCode::INVALID_TOKEN,
                    'Invalid or expired refresh token'
                );
            }

            // プレイヤーとデバイスを取得
            $sysPlayer = $this->sysPlayerRepository->selectById($oldToken->getPlayerId());

            // SysPlayerTokenからdevice_idを取得
            /** @var SysPlayerToken $oldToken */
            $deviceId = $oldToken->getSysPlayerDeviceId();
            $sysPlayerDevice = SysPlayerDevice::find($deviceId);

            if ($sysPlayer === null || $sysPlayerDevice === null) {
                throw new GameException(
                    GameErrorCode::PLAYER_NOT_FOUND,
                    'Player or device not found'
                );
            }

            // トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
            [$token, $newToken] = $this->tokenService->rotateToken(
                $oldToken,
                $sysPlayer,
                $sysPlayerDevice,
                fn ($playerId, $deviceId, $tokenHash, $expiresAt) => $this->newSysPlayerToken(
                    $playerId,
                    $deviceId,
                    $tokenHash,
                    $expiresAt
                )
            );

            // 最終ログイン日時を更新
            $this->playerAuthService->updateLastLogin($sysPlayerDevice->getUuid());

            return $token;
        });

        return RefreshTokenResponse::from($token);
    }
}
