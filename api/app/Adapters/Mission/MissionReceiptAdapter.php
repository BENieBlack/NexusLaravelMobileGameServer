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
    public static function toDto(TrxMission $model): MissionReceipt
    {
        return new MissionReceipt(
            sysPlayerId: $model->getSysPlayerId(),
            mstMissionId: $model->getMstMissionId(),
            receivedAt: $model->getReceivedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxMission>  $models
     * @return array<int, MissionReceipt>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];

        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
