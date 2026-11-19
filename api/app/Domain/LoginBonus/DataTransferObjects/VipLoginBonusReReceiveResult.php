<?php

namespace App\Domain\LoginBonus\DataTransferObjects;

use App\Models\Trx\TrxVipLoginBonus;

/**
 * VipLoginBonusReReceiveResult
 *
 * VIP の再受け取りの判定結果（judge の戻り値）。can_re_receive と receive の再判定が同じ結果を使う
 */
class VipLoginBonusReReceiveResult
{
    /**
     * @param  bool  $canReReceive  ①〜④（receive では ID 一致も）をすべて満たすか
     * @param  string|null  $reason  満たさなかった最初の理由（LoginBonusConst）。満たすなら null
     * @param  int  $vipLevel  今の VIP レベル（sys_player.vip_point から算出）
     * @param  array<string, mixed>|null  $master  今のレベルの有効な mst_vip_login_bonus（無ければ null）
     * @param  TrxVipLoginBonus|null  $trxVipLoginBonus  受け取り状態の行（無ければ null）
     * @param  array<int, array<string, mixed>>  $rewardRows  据え置いた日数の新レベルの報酬行（④ まで進んだときだけ入る）
     */
    public function __construct(
        public readonly bool $canReReceive,
        public readonly ?string $reason,
        public readonly int $vipLevel,
        public readonly ?array $master,
        public readonly ?TrxVipLoginBonus $trxVipLoginBonus,
        public readonly array $rewardRows,
    ) {}
}
