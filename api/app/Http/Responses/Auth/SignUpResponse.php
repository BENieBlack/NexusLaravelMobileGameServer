<?php

namespace App\Http\Responses\Auth;

use App\Domain\Auth\DataTransferObjects\SignUpResult;
use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use NexusAuth\Contracts\TokenModelInterface;
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
     * @param  TokenModelInterface  $sysPlayerToken  トークン情報
     * @param  Token  $token  トークン情報DTO
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly SysPlayerDevice $sysPlayerDevice,
        public readonly TokenModelInterface $sysPlayerToken,
        public readonly Token $token,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function fromResult(SignUpResult $result): self
    {
        return new self(
            sysPlayer: $result->sysPlayer,
            sysPlayerDevice: $result->sysPlayerDevice,
            sysPlayerToken: $result->sysPlayerToken,
            token: $result->token,
        );
    }

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'sys_player' => $this->sysPlayer->toArray(),
            'sys_player_device' => $this->sysPlayerDevice->toArray(),
            'sys_player_token' => $this->sysPlayerToken->toArray(),
            'token' => $this->token->toArray(),
        ];
    }
}
