<?php

namespace App\Domain\Auth\Responses;

use App\Domain\_BaseResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use NexusAuth\ValueObjects\Token;

/**
 * SignUpResponse
 *
 * サインアップAPIのレスポンス
 * sys_player, sys_player_device, sys_player_token の構造体と token を返す
 */
class SignUpResponse extends _BaseResponse
{
    /**
     * @param  SysPlayer  $sysPlayer  プレイヤー情報
     * @param  SysPlayerDevice  $sysPlayerDevice  デバイス情報
     * @param  SysPlayerToken  $sysPlayerToken  トークン情報
     * @param  Token  $token  トークン情報DTO
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly SysPlayerDevice $sysPlayerDevice,
        public readonly SysPlayerToken $sysPlayerToken,
        public readonly Token $token,
    ) {}

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'sys_player' => $this->sysPlayer->toArray(),
            'sys_player_device' => $this->sysPlayerDevice->toArray(),
            // リフレッシュトークンのハッシュは、サーバー側の照合用の値なので、クライアントには返さない（トークン本体は token にある）
            'sys_player_token' => array_diff_key($this->sysPlayerToken->toArray(), ['refresh_token_hash' => true]),
            'token' => $this->token->toArray(),
        ];
    }
}
