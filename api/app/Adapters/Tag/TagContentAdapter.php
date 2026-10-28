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
    public static function toDto(MstTagContent $model): TagContent
    {
        return new TagContent(
            mstTagId: $model->getMstTagId(),
            contentType: $model->getContentType(),
            contentMstId: $model->getContentMstId(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTagContent>  $models
     * @return array<int, TagContent>
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
