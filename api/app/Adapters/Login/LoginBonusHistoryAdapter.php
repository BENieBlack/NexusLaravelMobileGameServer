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
    public static function toDto(TrxLoginBonus $model): LoginBonusHistory
    {
        return new LoginBonusHistory(
            sysPlayerId: $model->getSysPlayerId(),
            type: $model->getType(),
            mstLoginBonusId: $model->getMstLoginBonusId(),
            day: $model->getDay(),
            absentDays: $model->getAbsentDays(),
            receivedDate: $model->getReceivedDate(),
        );
    }
}
