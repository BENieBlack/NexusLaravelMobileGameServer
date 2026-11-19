<?php

namespace App\Domain\Common\DataTransferObjects;

/**
 * VipLoginBonusCalendarResult
 *
 * VIP ログインボーナスの段の状態（API の vip_login_bonus_calendar の元）。
 * 通常の段に、今の VIP レベルと再受け取り可否を足したもの
 */
class VipLoginBonusCalendarResult
{
    /**
     * @param  int  $vipLevel  今の VIP レベル（受取済みの行の vip_level ではない）
     * @param  bool  $canReReceive  再受け取りが可能か（要件 R4 ①〜④ をすべて満たす）
     * @param  array<int, LoginBonusDayRewardResult>  $dayRewardList  1〜loop_days の全日（昇順）
     */
    public function __construct(
        public readonly string $mstVipLoginBonusId,
        public readonly int $loopDays,
        public readonly int $vipLevel,
        public readonly ?int $lastReceivedDay,
        public readonly bool $isGrantedToday,
        public readonly bool $isGrantedNow,
        public readonly bool $canReReceive,
        public readonly array $dayRewardList,
    ) {}
}
