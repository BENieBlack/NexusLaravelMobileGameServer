<?php

namespace App\Adapters\Trade;

use App\Models\Trx\TrxTradeProduct;
use NexusTrade\DataTransferObjects\TradeProductCount;

/**
 * TradeProductCountAdapter
 *
 * TrxTradeProduct Model と TradeProductCount の変換を行うアダプター
 */
class TradeProductCountAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxTradeProduct $model): TradeProductCount
    {
        return new TradeProductCount(
            sysPlayerId: $model->getSysPlayerId(),
            mstTradeProductId: $model->getMstTradeProductId(),
            tradeCount: $model->getTradeCount(),
            lastTradedAt: $model->getLastTradedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxTradeProduct>  $models
     * @return array<int, TradeProductCount>
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
