<?php

namespace App\Domain\LoginBonus\Contracts;

use App\Domain\LoginBonus\DataTransferObjects\LoginBonusCalendarResult;
use App\Domain\LoginBonus\DataTransferObjects\LoginBonusDayRewardResult;
use App\Domain\LoginBonus\DataTransferObjects\LoginBonusRewardResult;
use App\Domain\LoginBonus\DataTransferObjects\VipLoginBonusCalendarResult;

/**
 * LoginBonusCalendarFormatter
 *
 * カレンダーの状態（DTO）を API の形（login_bonus_calendar / vip_login_bonus_calendar）に整形する、状態の無い部品
 *
 * 他のコンテキスト（Login の HomeResponse）の Response からも使うので、公開 API の置き場 Contracts に置く
 * （他コンテキストが参照してよいのは Contracts と DataTransferObjects だけ。ModuleBoundaryTest）。
 *
 * login（HomeResponse）・POST /login_bonus/list（ListResponse）・POST /login_bonus/receive（ReceiveResponse）が
 * これを呼ぶので、3 つの API で形が必ず同じになる。DTO → 配列の整形はここだけに置く。
 */
class LoginBonusCalendarFormatter
{
    /**
     * 通常の段
     *
     * @return array<string, mixed>|null
     */
    public static function toArray(?LoginBonusCalendarResult $calendar): ?array
    {
        if ($calendar === null) {
            return null;
        }

        return [
            'mst_login_bonus_id' => $calendar->mstLoginBonusId,
            'loop_days' => $calendar->loopDays,
            'last_received_day' => $calendar->lastReceivedDay,
            'is_granted_today' => $calendar->isGrantedToday,
            'is_granted_now' => $calendar->isGrantedNow,
            'day_reward_list' => self::dayRewardList($calendar->dayRewardList),
        ];
    }

    /**
     * VIP の段
     *
     * @return array<string, mixed>|null
     */
    public static function toVipArray(?VipLoginBonusCalendarResult $calendar): ?array
    {
        if ($calendar === null) {
            return null;
        }

        return [
            'mst_vip_login_bonus_id' => $calendar->mstVipLoginBonusId,
            'loop_days' => $calendar->loopDays,
            'vip_level' => $calendar->vipLevel,
            'last_received_day' => $calendar->lastReceivedDay,
            'is_granted_today' => $calendar->isGrantedToday,
            'is_granted_now' => $calendar->isGrantedNow,
            'can_re_receive' => $calendar->canReReceive,
            'day_reward_list' => self::dayRewardList($calendar->dayRewardList),
        ];
    }

    /**
     * @param  array<int, LoginBonusDayRewardResult>  $dayRewardList
     * @return array<int, array<string, mixed>>
     */
    private static function dayRewardList(array $dayRewardList): array
    {
        return array_map(
            fn (LoginBonusDayRewardResult $day) => [
                'day' => $day->day,
                'reward_list' => array_map(
                    fn (LoginBonusRewardResult $reward) => [
                        'type' => $reward->type,
                        'id' => $reward->id,
                        'amount' => $reward->amount,
                        'metadata' => $reward->metadata,
                    ],
                    $day->rewardList
                ),
            ],
            array_values($dayRewardList)
        );
    }
}
