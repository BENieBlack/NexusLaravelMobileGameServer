<?php

namespace App\Adapters\Trade;

use App\Models\Mst\MstTradeProduct;
use NexusTrade\DataTransferObjects\TradeProduct;

/**
 * TradeProductAdapter
 *
 * MstTradeProduct Model と TradeProduct の変換を行うアダプター
 */
class TradeProductAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstTradeProduct $model): TradeProduct
    {
        return new TradeProduct(
            id: $model->getId(),
            mstTradeId: $model->getMstTradeId(),
            costType: $model->getCostType(),
            costMstId: $model->getCostMstId(),
            costAmount: $model->getCostAmount(),
            tradeLimit: $model->getTradeLimit(),
            resetType: $model->getResetType(),
            startAt: $model->getStartAt(),
            endAt: $model->getEndAt(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTradeProduct>  $models
     * @return array<int, TradeProduct>
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
