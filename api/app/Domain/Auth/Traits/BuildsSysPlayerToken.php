<?php

namespace App\Domain\Auth\Traits;

use App\Models\Sys\SysPlayerToken;

/**
 * BuildsSysPlayerToken
 *
 * トークン発行系UseCaseで共通利用するトークンModelのファクトリ。
 *
 * TokenServiceに渡すファクトリは未保存のModelを返すだけでよく、
 * 永続化はTokenService内のsetModel()でUnitOfWorkにキューイングされる。
 */
trait BuildsSysPlayerToken
{
    /**
     * 未保存のトークンModelを生成する
     */
    protected function newSysPlayerToken(
        int $playerId,
        int $deviceId,
        string $tokenHash,
        string $expiresAt
    ): SysPlayerToken {
        $sysPlayerToken = new SysPlayerToken([
            'sys_player_id' => $playerId,
            'sys_player_device_id' => $deviceId,
            'refresh_token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
        $sysPlayerToken->exists = false;

        return $sysPlayerToken;
    }
}
