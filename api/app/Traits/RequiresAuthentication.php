<?php

namespace App\Traits;

use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;

/**
 * RequiresAuthentication
 * 
 * 認証が必要なUseCaseで使用するTrait
 * プレイヤーIDの取得と検証を共通化
 */
trait RequiresAuthentication
{
    /**
     * 認証済みプレイヤーIDを取得（未認証の場合は例外）
     *
     * @param mixed $request リクエストオブジェクト
     * @return int プレイヤーID
     * @throws GameException 認証失敗時
     */
    protected function getAuthenticatedPlayerIdOrFail($request): int
    {
        // getAuthenticatedPlayerId() メソッドが存在するか確認
        if (!method_exists($request, 'getAuthenticatedPlayerId')) {
            throw new GameException(
                GameErrorCode::INTERNAL_ERROR,
                'Request does not support authentication'
            );
        }

        $playerId = $request->getAuthenticatedPlayerId();
        
        if (!$playerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        return $playerId;
    }

    /**
     * 認証済みプレイヤーIDを取得（未認証の場合はnull）
     *
     * @param mixed $request リクエストオブジェクト
     * @return int|null プレイヤーID（未認証の場合はnull）
     */
    protected function getAuthenticatedPlayerId($request): ?int
    {
        if (!method_exists($request, 'getAuthenticatedPlayerId')) {
            return null;
        }

        return $request->getAuthenticatedPlayerId();
    }
}
