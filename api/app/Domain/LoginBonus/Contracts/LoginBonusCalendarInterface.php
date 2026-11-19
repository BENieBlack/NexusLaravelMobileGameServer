<?php

namespace App\Domain\LoginBonus\Contracts;

use App\Domain\LoginBonus\DataTransferObjects\LoginBonusCalendarsResult;

/**
 * LoginBonusCalendarInterface
 *
 * LoginBonus ドメインの公開 API。Login（HomeUseCase）が使う
 */
interface LoginBonusCalendarInterface
{
    /**
     * 2 つの段（通常・VIP）の状態を返す。段のマスターが無ければ各々 null
     *
     * DB へは書かない。API の形への整形は LoginBonusCalendarFormatter（Response 側）が行う。
     * login から呼ぶときは、配布と last_login_at の更新の「後」に呼ぶこと（配布前だと最後に配った日数が古い）
     *
     * @param  bool  $isNormalGrantedNowAllowed  通常の段の is_granted_now を真にしてよいか
     * @param  bool  $isVipGrantedNowAllowed  VIP の段の is_granted_now を真にしてよいか
     */
    public function findCalendars(
        int $sysPlayerId,
        bool $isNormalGrantedNowAllowed,
        bool $isVipGrantedNowAllowed,
    ): LoginBonusCalendarsResult;
}
