<?php

namespace App\Domain\LoginBonus\Responses;

use App\Domain\_BaseResponse;
use App\Domain\LoginBonus\Contracts\LoginBonusCalendarFormatter;
use App\Domain\LoginBonus\DataTransferObjects\VipLoginBonusCalendarResult;
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
    use HasUpdatedResources;

    /**
     * @param  array<int, resource>  $loginBonusContents  今回配った報酬
     */
    public function __construct(
        private readonly TrxVipLoginBonus $trxVipLoginBonus,
        private readonly array $loginBonusContents,
        private readonly ?VipLoginBonusCalendarResult $vipCalendar,
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
            'vip_login_bonus_calendar' => LoginBonusCalendarFormatter::toVipArray($this->vipCalendar),
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
