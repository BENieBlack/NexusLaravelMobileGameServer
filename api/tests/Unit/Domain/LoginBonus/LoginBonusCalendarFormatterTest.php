<?php

namespace Tests\Unit\Domain\LoginBonus;

use App\Domain\LoginBonus\Contracts\LoginBonusCalendarFormatter;
use App\Domain\LoginBonus\DataTransferObjects\LoginBonusCalendarResult;
use App\Domain\LoginBonus\DataTransferObjects\LoginBonusDayRewardResult;
use App\Domain\LoginBonus\DataTransferObjects\LoginBonusRewardResult;
use App\Domain\LoginBonus\DataTransferObjects\VipLoginBonusCalendarResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LoginBonusCalendarFormatter のテスト
 *
 * login・list・receive が共有する API の形（キー名・並び・null）を固定する。
 */
class LoginBonusCalendarFormatterTest extends TestCase
{
    #[Test]
    public function 段が無ければnull(): void
    {
        $this->assertNull(LoginBonusCalendarFormatter::toArray(null));
        $this->assertNull(LoginBonusCalendarFormatter::toVipArray(null));
    }

    #[Test]
    public function 通常の段は設計のキーで整形される(): void
    {
        $calendar = new LoginBonusCalendarResult(
            mstLoginBonusId: 'daily',
            loopDays: 2,
            lastReceivedDay: null,
            isGrantedToday: false,
            isGrantedNow: false,
            dayRewardList: [
                new LoginBonusDayRewardResult(1, [new LoginBonusRewardResult('item', 'free_diamond', 100, null)]),
                new LoginBonusDayRewardResult(2, []),
            ],
        );

        $this->assertSame([
            'mst_login_bonus_id' => 'daily',
            'loop_days' => 2,
            'last_received_day' => null,
            'is_granted_today' => false,
            'is_granted_now' => false,
            'day_reward_list' => [
                ['day' => 1, 'reward_list' => [['type' => 'item', 'id' => 'free_diamond', 'amount' => 100, 'metadata' => null]]],
                ['day' => 2, 'reward_list' => []],
            ],
        ], LoginBonusCalendarFormatter::toArray($calendar));
    }

    #[Test]
    public function vi_pの段は_i_dのキーが違い_vip_levelとcan_re_receiveを持つ(): void
    {
        $calendar = new VipLoginBonusCalendarResult(
            mstVipLoginBonusId: 'vip_login_lv5',
            loopDays: 7,
            vipLevel: 5,
            lastReceivedDay: 2,
            isGrantedToday: true,
            isGrantedNow: true,
            canReReceive: true,
            dayRewardList: [],
        );

        $array = LoginBonusCalendarFormatter::toVipArray($calendar);

        $this->assertSame(
            ['mst_vip_login_bonus_id', 'loop_days', 'vip_level', 'last_received_day', 'is_granted_today', 'is_granted_now', 'can_re_receive', 'day_reward_list'],
            array_keys((array) $array)
        );
        $this->assertTrue($array['can_re_receive']);
        $this->assertSame([], $array['day_reward_list']);
    }
}
