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
    public static function toDto(MstEventContent $mstEventContent): EventContent
    {
        return new EventContent(
            mstEventId: $mstEventContent->getMstEventId(),
            contentType: $mstEventContent->getContentType(),
            contentMstId: $mstEventContent->getContentMstId(),
            sortOrder: $mstEventContent->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstEventContent>  $mstEventContents
     * @return array<int, EventContent>
     */
    public static function toDtoArray(iterable $mstEventContents): array
    {
        $dtos = [];

        foreach ($mstEventContents as $mstEventContent) {
            $dtos[] = self::toDto($mstEventContent);
        }

        return $dtos;
    }
}
