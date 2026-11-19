<?php

namespace App\Domain\LoginBonus\Responses;

use App\Domain\_BaseResponse;
use App\Domain\Common\DataTransferObjects\LoginBonusCalendarsResult;
use App\Domain\Common\Support\LoginBonusCalendarFormatter;

/**
 * ListResponse
 *
 * POST /login_bonus/list のレスポンス。通常・VIP の 2 つの段（マスターが無い段は null）
 * 読み取りだけの API なので updated_resources は返さない
 */
class ListResponse extends _BaseResponse
{
    public function __construct(
        private readonly LoginBonusCalendarsResult $calendars,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'login_bonus_calendar' => LoginBonusCalendarFormatter::toArray($this->calendars->normal),
            'vip_login_bonus_calendar' => LoginBonusCalendarFormatter::toVipArray($this->calendars->vip),
        ];
    }
}
