<?php

namespace App\Adapters\Trade;

use App\Models\Mst\MstTrade;
use NexusTrade\DataTransferObjects\Trade;

/**
 * TradeAdapter
 *
 * MstTrade Model と Trade の変換を行うアダプター
 */
class TradeAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(MstTrade $mstTrade): Trade
    {
        return new Trade(
            id: $mstTrade->getId(),
            startAt: $mstTrade->getStartAt(),
            endAt: $mstTrade->getEndAt(),
            sortOrder: $mstTrade->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTrade>  $mstTrades
     * @return array<int, Trade>
     */
    public static function toDtoArray(iterable $mstTrades): array
    {
        $dtos = [];

        foreach ($mstTrades as $mstTrade) {
            $dtos[] = self::toDto($mstTrade);
        }

        return $dtos;
    }
}
