<?php

namespace App\Adapters\Login;

use App\Models\Trx\TrxLoginBonus;
use NexusLogin\DataTransferObjects\LoginBonusHistory;

/**
 * LoginBonusHistoryAdapter
 *
 * TrxLoginBonus Model と LoginBonusHistory の変換を行うアダプター
 */
class LoginBonusHistoryAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxLoginBonus $trxLoginBonus): LoginBonusHistory
    {
        return new LoginBonusHistory(
            sysPlayerId: $trxLoginBonus->getSysPlayerId(),
            type: $trxLoginBonus->getType(),
            mstLoginBonusId: $trxLoginBonus->getMstLoginBonusId(),
            day: $trxLoginBonus->getDay(),
            absentDays: $trxLoginBonus->getAbsentDays(),
            receivedDate: $trxLoginBonus->getReceivedDate(),
        );
    }
}
