<?php

namespace App\Adapters\Mission;

use App\Models\Mst\MstMission;
use NexusMission\DataTransferObjects\Mission;

/**
 * MissionAdapter
 *
 * MstMission Model と Mission の変換を行うアダプター
 */
class MissionAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstMission $model): Mission
    {
        return new Mission(
            id: $model->getId(),
            triggerType: $model->getTriggerType(),
            mstTagId: $model->getMstTagId(),
            targetValue: $model->getTargetValue(),
            startAt: $model->getStartAt(),
            endAt: $model->getEndAt(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstMission>  $models
     * @return array<int, Mission>
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
