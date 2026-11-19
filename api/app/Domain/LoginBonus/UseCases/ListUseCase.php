<?php

namespace App\Domain\LoginBonus\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\LoginBonus\Contracts\LoginBonusCalendarInterface;
use App\Domain\LoginBonus\Responses\ListResponse;
use App\Exceptions\SystemDataException;
use App\Repositories\Sys\SysPlayerRepository;

/**
 * ListUseCase
 *
 * ログインボーナスのカレンダー（通常・VIP の 2 つの段）の最新の状態を返す
 *
 * DB へは書かない（executeWithTransaction を使わない。updated_resources も返さない）。
 * is_granted_now は常に偽（スタンプのアニメーションは login と再受け取りの応答でだけ再生する）。
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly LoginBonusCalendarInterface $loginBonusCalendar,
    ) {}

    /**
     * @throws SystemDataException プレイヤーが存在しない場合
     */
    public function validation(int $sysPlayerId): void
    {
        if ($this->sysPlayerRepository->selectById($sysPlayerId) === null) {
            throw SystemDataException::generic('sys_player', $sysPlayerId);
        }
    }

    public function handle(int $sysPlayerId): ListResponse
    {
        $this->validation($sysPlayerId);

        // 状態を小さな読み取りに分けて取り、is_granted_today などの組み立ては Response の引数にする。
        // is_granted_now は常に偽（Response 側で固定）
        $normalHistory = $this->loginBonusCalendar->findNormalHistory($sysPlayerId);
        $vipHistory = $this->loginBonusCalendar->findVipHistory($sysPlayerId);

        return new ListResponse(
            normalBonusId: $this->loginBonusCalendar->findNormalBonusId(),
            normalLastReceivedDay: $normalHistory?->getDay(),
            isNormalGrantedToday: $this->loginBonusCalendar->isReceivedToday($normalHistory?->getReceivedDate()),
            vipBonusId: $this->loginBonusCalendar->findVipBonusId($sysPlayerId),
            vipLevel: $this->loginBonusCalendar->findVipLevel($sysPlayerId),
            vipLastReceivedDay: $vipHistory?->getDay(),
            isVipGrantedToday: $this->loginBonusCalendar->isReceivedToday($vipHistory?->getReceivedAt()),
            canVipReReceive: $this->loginBonusCalendar->canVipReReceive($sysPlayerId),
        );
    }
}
