<?php

namespace App\Domain\LoginBonus\Responses;

use App\Domain\_BaseResponse;
use App\Domain\LoginBonus\Support\BuildsCalendarStage;

/**
 * ListResponse
 *
 * POST /login_bonus/list のレスポンス。通常・VIP の 2 つの段（マスターが無い段は null）
 * 読み取りだけの API なので updated_resources は返さず、is_granted_now は常に偽
 */
class ListResponse extends _BaseResponse
{
    use BuildsCalendarStage;

    public function __construct(
        private readonly ?string $normalBonusId,
        private readonly ?int $normalLastReceivedDay,
        private readonly bool $isNormalGrantedToday,
        private readonly ?string $vipBonusId,
        private readonly int $vipLevel,
        private readonly ?int $vipLastReceivedDay,
        private readonly bool $isVipGrantedToday,
        private readonly bool $canVipReReceive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'login_bonus_calendar' => $this->normalStage($this->normalBonusId, $this->normalLastReceivedDay, $this->isNormalGrantedToday, false),
            'vip_login_bonus_calendar' => $this->vipStage($this->vipBonusId, $this->vipLevel, $this->vipLastReceivedDay, $this->isVipGrantedToday, false, $this->canVipReReceive),
        ];
    }
}
