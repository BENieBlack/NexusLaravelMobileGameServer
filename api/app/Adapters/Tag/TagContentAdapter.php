<?php

namespace App\Adapters\Tag;

use App\Models\Mst\MstTagContent;
use NexusTag\DataTransferObjects\TagContent;

/**
 * TagContentAdapter
 *
 * MstTagContent Model と TagContent の変換を行うアダプター
 */
class TagContentAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstTagContent $mstTagContent): TagContent
    {
        return new TagContent(
            mstTagId: $mstTagContent->getMstTagId(),
            contentType: $mstTagContent->getContentType(),
            contentMstId: $mstTagContent->getContentMstId(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTagContent>  $mstTagContents
     * @return array<int, TagContent>
     */
    public static function toDtoArray(iterable $mstTagContents): array
    {
        $dtos = [];

        foreach ($mstTagContents as $mstTagContent) {
            $dtos[] = self::toDto($mstTagContent);
        }

        return $dtos;
    }
}
