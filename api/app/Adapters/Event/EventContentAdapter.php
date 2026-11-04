<?php

namespace App\Adapters\Event;

use App\Models\Mst\MstEventContent;
use NexusEvent\DataTransferObjects\EventContent;

/**
 * EventContentAdapter
 *
 * MstEventContent Model と EventContent の変換を行うアダプター
 */
class EventContentAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstEventContent $model): EventContent
    {
        return new EventContent(
            mstEventId: $model->getMstEventId(),
            contentType: $model->getContentType(),
            contentMstId: $model->getContentMstId(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstEventContent>  $models
     * @return array<int, EventContent>
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
