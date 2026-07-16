<?php

namespace App\Traits;

use NexusAuth\Services\TokenService;
use NexusAuth\Contracts\TokenModelInterface;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;

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
     * @param TokenService $tokenService
     * @param string $refreshToken
     * @return TokenModelInterface
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
     *
     * @param TokenService $tokenService
     * @param string $refreshToken
     * @return TokenModelInterface|null
     */
    protected function validateRefreshToken(TokenService $tokenService, string $refreshToken): ?TokenModelInterface
    {
        return $tokenService->validateRefreshToken($refreshToken);
    }
}
