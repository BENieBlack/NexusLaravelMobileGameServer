<?php

namespace App\Adapters\Trade;

use App\Models\Mst\MstTradeProductContent;
use NexusTrade\DataTransferObjects\TradeProductContent;

/**
 * TradeProductContentAdapter
 *
 * MstTradeProductContent Model と TradeProductContent の変換を行うアダプター
 */
class TradeProductContentAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstTradeProductContent $model): TradeProductContent
    {
        return new TradeProductContent(
            mstTradeProductId: $model->getMstTradeProductId(),
            contentType: $model->getContentType(),
            contentMstId: $model->getContentMstId(),
            contentQuantity: $model->getContentQuantity(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTradeProductContent>  $models
     * @return array<int, TradeProductContent>
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
