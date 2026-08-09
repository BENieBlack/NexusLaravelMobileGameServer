<?php

namespace App\Traits;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusAuth\Contracts\TokenModelInterface;
use NexusAuth\Services\TokenService;

/**
 * RequiresRefreshTokenTrait
 *
 * リフレッシュトークンの検証が必要なUseCaseで使用するTrait
 * リフレッシュトークンの検証処理を共通化
 */
trait RequiresRefreshTokenTrait
{
    /**
     * リフレッシュトークンを検証して取得（無効な場合は例外）
     *
     * @throws GameException リフレッシュトークンが無効または期限切れの場合
     */
    protected function validateRefreshTokenOrFail(TokenService $tokenService, string $refreshToken): TokenModelInterface
    {
        $sysPlayerToken = $tokenService->validateRefreshToken($refreshToken);

        if ($sysPlayerToken === null) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Invalid or expired refresh token'
            );
        }

        return $sysPlayerToken;
    }

    /**
     * リフレッシュトークンを検証して取得（無効な場合はnull）
     */
    protected function validateRefreshToken(TokenService $tokenService, string $refreshToken): ?TokenModelInterface
    {
        return $tokenService->validateRefreshToken($refreshToken);
    }
}
