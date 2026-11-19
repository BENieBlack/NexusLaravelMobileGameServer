<?php

namespace App\Domain\Auth\DataTransferObjects;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use NexusAuth\ValueObjects\Token;

/**
 * SignInResult
 *
 * AccountTransferService::transfer() の結果（端末の付け替えとトークン発行で得た 4 つの値）
 *
 * Result を残す理由（coding-standards.md「DTO / Result」の条件）:
 * - Service の戻り値を 1 つにできない（Model 3 つと Token DTO を同時に返す。transfer() は 1 つの
 *   トランザクション内の一連の書き込みなので、分割して UseCase に組み合わせさせると順序と整合性が UseCase に漏れる）
 * - 呼び出し元が 2 か所ある（TransferByCodeUseCase / TransferByExternalAccountUseCase）
 * Model を持つ内部用の Result だが、Support ではなく DataTransferObjects に置く: Service（Auth）と、それを使う
 *   Account 系の UseCase 2 つにまたがるため。1 つの UseCase 系統の内部でしか使わない Result なら Support に置いてよい（coding-standards.md）
 * サインイン（SignInUseCase）は呼び出し元が 1 か所のため、Result を使わず Response を直接組む
 */
class SignInResult
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
}
