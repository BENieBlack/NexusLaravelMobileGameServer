<?php

namespace App\Adapters\Mission;

use App\Models\Trx\TrxMission;
use NexusMission\DataTransferObjects\MissionReceipt;

/**
 * MissionReceiptAdapter
 *
 * TrxMission Model と MissionReceipt の変換を行うアダプター
 */
class MissionReceiptAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxMission $trxMission): MissionReceipt
    {
        return new MissionReceipt(
            sysPlayerId: $trxMission->getSysPlayerId(),
            mstMissionId: $trxMission->getMstMissionId(),
            receivedAt: $trxMission->getReceivedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxMission>  $trxMissions
     * @return array<int, MissionReceipt>
     */
    public static function toDtoArray(iterable $trxMissions): array
    {
        $dtos = [];

        foreach ($trxMissions as $trxMission) {
            $dtos[] = self::toDto($trxMission);
        }

        return $dtos;
    }
}
