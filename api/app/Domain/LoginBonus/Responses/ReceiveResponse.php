<?php

namespace App\Domain\LoginBonus\Responses;

use App\Domain\_BaseResponse;
use App\Domain\LoginBonus\Support\BuildsCalendarStage;
use App\Domain\ReturnsUpdatedResources;
use App\Models\Trx\TrxVipLoginBonus;
use App\Traits\HasUpdatedResources;
use Nexus\Core\Resource\DataTransferObjects\Resource;

/**
 * ReceiveResponse
 *
 * POST /login_bonus/receive（VIP の再受け取り）のレスポンス
 *
 * trx_vip_login_bonus は updated_resources の対象テーブルではないので、独立したキーで返す。
 * login_bonus_list（今回配った報酬）と vip_login_bonus_calendar は、画面の「今日配られた報酬」の行と
 * VIP の段の更新のために足している（仮置き。設計 U-S2-6）。
 */
class ReceiveResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use BuildsCalendarStage;
    use HasUpdatedResources;

    /**
     * @param  array<int, resource>  $loginBonusContents  今回配った報酬
     */
    public function __construct(
        private readonly TrxVipLoginBonus $trxVipLoginBonus,
        private readonly array $loginBonusContents,
        private readonly string $vipBonusId,
        private readonly int $vipLevel,
        private readonly int $lastReceivedDay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trx_vip_login_bonus' => $this->trxVipLoginBonus->toResponseArray(),
            'login_bonus_list' => array_map(
                fn (Resource $resource) => $resource->toArray(),
                $this->loginBonusContents
            ),
            'vip_login_bonus_calendar' => // 再受け取りの直後: 今日配った（is_granted_today・is_granted_now とも真）。
            // 受取時のレベルが今のレベルになったので、もう再受け取りできない（can_re_receive は偽）
            $this->vipStage($this->vipBonusId, $this->vipLevel, $this->lastReceivedDay, true, true, false),
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
