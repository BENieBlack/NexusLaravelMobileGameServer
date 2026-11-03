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
    public static function toDto(MstTrade $model): Trade
    {
        return new Trade(
            id: $model->getId(),
            startAt: $model->getStartAt(),
            endAt: $model->getEndAt(),
            sortOrder: $model->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTrade>  $models
     * @return array<int, Trade>
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
