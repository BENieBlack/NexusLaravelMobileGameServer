<?php

namespace App\Adapters\Item;

use App\Models\Trx\TrxItem;
use NexusResource\DataTransferObjects\Item;

/**
 * ItemAdapter
 *
 * TrxItem Model と Item の変換を行うアダプター
 */
class ItemAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxItem $model): Item
    {
        return new Item(
            sysPlayerId: $model->getSysPlayerId(),
            mstItemId: $model->getMstItemId(),
            freeAmount: $model->getFreeAmount(),
            paidAmount: $model->getPaidAmount(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxItem>  $models
     * @return array<Item>
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
