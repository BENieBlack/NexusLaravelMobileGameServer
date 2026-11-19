<?php

namespace App\Domain\Common\DataTransferObjects;

/**
 * LoginBonusCalendarResult
 *
 * 通常ログインボーナスの段の状態（API の login_bonus_calendar の元）
 */
class LoginBonusCalendarResult
{
    /**
     * @param  int|null  $lastReceivedDay  最後に配った日数（1 度も配っていなければ null）
     * @param  array<int, LoginBonusDayRewardResult>  $dayRewardList  1〜loop_days の全日（昇順）
     */
    public function __construct(
        public readonly string $mstLoginBonusId,
        public readonly int $loopDays,
        public readonly ?int $lastReceivedDay,
        public readonly bool $isGrantedToday,
        public readonly bool $isGrantedNow,
        public readonly array $dayRewardList,
    ) {}
}
