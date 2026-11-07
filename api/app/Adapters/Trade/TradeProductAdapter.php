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
    public static function toDto(MstTradeProduct $mstTradeProduct): TradeProduct
    {
        return new TradeProduct(
            id: $mstTradeProduct->getId(),
            mstTradeId: $mstTradeProduct->getMstTradeId(),
            costType: $mstTradeProduct->getCostType(),
            costMstId: $mstTradeProduct->getCostMstId(),
            costAmount: $mstTradeProduct->getCostAmount(),
            tradeLimit: $mstTradeProduct->getTradeLimit(),
            resetType: $mstTradeProduct->getResetType(),
            startAt: $mstTradeProduct->getStartAt(),
            endAt: $mstTradeProduct->getEndAt(),
            sortOrder: $mstTradeProduct->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTradeProduct>  $mstTradeProducts
     * @return array<int, TradeProduct>
     */
    public static function toDtoArray(iterable $mstTradeProducts): array
    {
        $dtos = [];

        foreach ($mstTradeProducts as $mstTradeProduct) {
            $dtos[] = self::toDto($mstTradeProduct);
        }

        return $dtos;
    }
}
