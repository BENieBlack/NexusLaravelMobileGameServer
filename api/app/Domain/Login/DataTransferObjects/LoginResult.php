<?php

namespace App\Domain\Login\DataTransferObjects;

use App\Models\Sys\SysPlayer;
use App\Models\Trx\TrxItem;
use App\Models\Trx\TrxUnit;
use App\Models\Trx\TrxWallet;

/**
 * LoginResult
 *
 * ログイン処理の結果
 */
class LoginResult
{
    /**
     * @param  SysPlayer  $sysPlayer  プレイヤー情報
     * @param  array<int, TrxUnit>  $trxUnits  所持ユニット一覧
     * @param  array<int, TrxItem>  $trxItems  所持アイテム一覧
     * @param  array<int, TrxWallet>  $trxWallets  ウォレット一覧
     * @param  array<int, \NexusResource\DataTransferObjects\Resource>  $loginBonusContents  ログインボーナス内容
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly array $trxUnits,
        public readonly array $trxItems,
        public readonly array $trxWallets,
        public readonly array $loginBonusContents,
    ) {}
}
