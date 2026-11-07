<?php

namespace App\Domain\Auth\Responses;

use App\Domain\_BaseResponse;
use App\Domain\Auth\DataTransferObjects\SignInResult;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use NexusAuth\ValueObjects\Token;

/**
 * SignInResponse
 *
 * サインインAPIのレスポンス
 * sys_player, sys_player_device, sys_player_token の構造体と token を返す
 */
class SignInResponse extends _BaseResponse
{
    /**
     * @param  SysPlayer  $sysPlayer  プレイヤー情報
     * @param  SysPlayerDevice  $sysPlayerDevice  デバイス情報
     * @param  SysPlayerToken  $sysPlayerToken  トークン情報
     * @param  Token  $token  トークン情報DTO
     */
    final public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly SysPlayerDevice $sysPlayerDevice,
        public readonly SysPlayerToken $sysPlayerToken,
        public readonly Token $token,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function from(SignInResult $signInResult): static
    {
        return new static(
            sysPlayer: $signInResult->sysPlayer,
            sysPlayerDevice: $signInResult->sysPlayerDevice,
            sysPlayerToken: $signInResult->sysPlayerToken,
            token: $signInResult->token,
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
