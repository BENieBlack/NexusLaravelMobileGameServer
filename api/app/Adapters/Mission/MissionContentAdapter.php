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
    public static function toDto(MstMissionContent $model): MissionContent
    {
        return new MissionContent(
            mstMissionId: $model->getMstMissionId(),
            contentType: $model->getContentType(),
            contentMstId: $model->getContentMstId(),
            contentQuantity: $model->getContentQuantity(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstMissionContent>  $models
     * @return array<int, MissionContent>
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
