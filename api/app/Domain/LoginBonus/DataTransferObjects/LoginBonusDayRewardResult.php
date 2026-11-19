<?php

namespace App\Domain\LoginBonus\DataTransferObjects;

/**
 * LoginBonusDayRewardResult
 *
 * カレンダーの 1 日分（日数と、その日の報酬の一覧。報酬行が無い日は空）
 */
class LoginBonusDayRewardResult
{
    /**
     * @param  int  $day  日数（1〜loop_days）
     * @param  array<int, LoginBonusRewardResult>  $rewardList  配布と同じ順
     */
    public function __construct(
        public readonly int $day,
        public readonly array $rewardList,
    ) {}
}
