<?php

namespace App\Domain\Common\DataTransferObjects;

/**
 * LoginBonusCalendarsResult
 *
 * 2 つの段（通常・VIP）の状態をまとめたもの。マスターが無い段は null
 */
class LoginBonusCalendarsResult
{
    public function __construct(
        public readonly ?LoginBonusCalendarResult $normal,
        public readonly ?VipLoginBonusCalendarResult $vip,
    ) {}
}
