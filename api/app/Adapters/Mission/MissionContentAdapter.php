<?php

namespace App\Adapters\Mission;

use App\Models\Mst\MstMissionContent;
use NexusMission\DataTransferObjects\MissionContent;

/**
 * MissionContentAdapter
 *
 * MstMissionContent Model と MissionContent の変換を行うアダプター
 */
class MissionContentAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstMissionContent $mstMissionContent): MissionContent
    {
        return new MissionContent(
            mstMissionId: $mstMissionContent->getMstMissionId(),
            contentType: $mstMissionContent->getContentType(),
            contentMstId: $mstMissionContent->getContentMstId(),
            contentQuantity: $mstMissionContent->getContentQuantity(),
            sortOrder: $mstMissionContent->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstMissionContent>  $mstMissionContents
     * @return array<int, MissionContent>
     */
    public static function toDtoArray(iterable $mstMissionContents): array
    {
        $dtos = [];

        foreach ($mstMissionContents as $mstMissionContent) {
            $dtos[] = self::toDto($mstMissionContent);
        }

        return $dtos;
    }
}
