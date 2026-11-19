<?php

namespace App\Domain\LoginBonus\Support;

/**
 * BuildsCalendarStage
 *
 * LoginBonus の Response（ListResponse・ReceiveResponse）が、カレンダーの段（API の
 * login_bonus_calendar / vip_login_bonus_calendar）を組み立てる小さな部品。
 * 段のマスターが無い（ID が null）ときは null。login の HomeResponse は別のコンテキストなので
 * 同じ形を自分で組み立てる（形は docs/design/login-bonus-calendar/api.md 2 節）。
 */
trait BuildsCalendarStage
{
    /**
     * 通常の段
     *
     * @return array<string, mixed>|null
     */
    private function normalStage(?string $mstLoginBonusId, ?int $lastReceivedDay, bool $isGrantedToday, bool $isGrantedNow): ?array
    {
        if ($mstLoginBonusId === null) {
            return null;
        }

        return [
            'mst_login_bonus_id' => $mstLoginBonusId,
            'last_received_day' => $lastReceivedDay,
            'is_granted_today' => $isGrantedToday,
            'is_granted_now' => $isGrantedNow,
        ];
    }

    /**
     * VIP の段
     *
     * @return array<string, mixed>|null
     */
    private function vipStage(
        ?string $mstVipLoginBonusId,
        int $vipLevel,
        ?int $lastReceivedDay,
        bool $isGrantedToday,
        bool $isGrantedNow,
        bool $canReReceive,
    ): ?array {
        if ($mstVipLoginBonusId === null) {
            return null;
        }

        return [
            'mst_vip_login_bonus_id' => $mstVipLoginBonusId,
            'vip_level' => $vipLevel,
            'last_received_day' => $lastReceivedDay,
            'is_granted_today' => $isGrantedToday,
            'is_granted_now' => $isGrantedNow,
            'can_re_receive' => $canReReceive,
        ];
    }
}
