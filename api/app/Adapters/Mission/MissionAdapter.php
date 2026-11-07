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
    public static function toDto(MstMission $mstMission): Mission
    {
        return new Mission(
            id: $mstMission->getId(),
            triggerType: $mstMission->getTriggerType(),
            mstTagId: $mstMission->getMstTagId(),
            targetValue: $mstMission->getTargetValue(),
            startAt: $mstMission->getStartAt(),
            endAt: $mstMission->getEndAt(),
            sortOrder: $mstMission->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstMission>  $mstMissions
     * @return array<int, Mission>
     */
    public static function toDtoArray(iterable $mstMissions): array
    {
        $dtos = [];

        foreach ($mstMissions as $mstMission) {
            $dtos[] = self::toDto($mstMission);
        }

        return $dtos;
    }
}
