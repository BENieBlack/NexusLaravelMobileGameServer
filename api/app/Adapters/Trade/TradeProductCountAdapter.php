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
    public static function toDto(TrxTradeProduct $trxTradeProduct): TradeProductCount
    {
        return new TradeProductCount(
            sysPlayerId: $trxTradeProduct->getSysPlayerId(),
            mstTradeProductId: $trxTradeProduct->getMstTradeProductId(),
            tradeCount: $trxTradeProduct->getTradeCount(),
            lastTradedAt: $trxTradeProduct->getLastTradedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxTradeProduct>  $trxTradeProducts
     * @return array<int, TradeProductCount>
     */
    public static function toDtoArray(iterable $trxTradeProducts): array
    {
        $dtos = [];

        foreach ($trxTradeProducts as $trxTradeProduct) {
            $dtos[] = self::toDto($trxTradeProduct);
        }

        return $dtos;
    }
}
