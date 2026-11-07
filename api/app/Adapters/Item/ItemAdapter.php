<?php

namespace App\Adapters\Item;

use App\Models\Trx\TrxItem;
use Nexus\Core\Resource\DataTransferObjects\Item;

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
    public static function toDto(TrxItem $trxItem): Item
    {
        return new Item(
            sysPlayerId: $trxItem->getSysPlayerId(),
            mstItemId: $trxItem->getMstItemId(),
            freeAmount: $trxItem->getFreeAmount(),
            paidAmount: $trxItem->getPaidAmount(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxItem>  $trxItems
     * @return array<Item>
     */
    public static function toDtoArray(iterable $trxItems): array
    {
        $dtos = [];
        foreach ($trxItems as $trxItem) {
            $dtos[] = self::toDto($trxItem);
        }

        return $dtos;
    }
}
