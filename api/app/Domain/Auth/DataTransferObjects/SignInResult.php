<?php

namespace App\Domain\Auth\DataTransferObjects;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use NexusAuth\Contracts\TokenModelInterface;
use NexusAuth\ValueObjects\Token;

/**
 * SignInResult
 *
 * サインインの結果
 */
class SignInResult
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
}
