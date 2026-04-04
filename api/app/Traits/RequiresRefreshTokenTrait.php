<?php

namespace App\Traits;

use App\Domain\Auth\Services\TokenService;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;
use App\Models\Sys\SysPlayerToken;

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
     * @return SysPlayerToken
     * @throws GameException リフレッシュトークンが無効または期限切れの場合
     */
    protected function validateRefreshTokenOrFail(TokenService $tokenService, string $refreshToken): SysPlayerToken
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
     * @return SysPlayerToken|null
     */
    protected function validateRefreshToken(TokenService $tokenService, string $refreshToken): ?SysPlayerToken
    {
        return $tokenService->validateRefreshToken($refreshToken);
    }
}
