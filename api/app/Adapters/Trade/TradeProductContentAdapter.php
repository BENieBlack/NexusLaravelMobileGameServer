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
    public static function toDto(MstTradeProductContent $mstTradeProductContent): TradeProductContent
    {
        return new TradeProductContent(
            mstTradeProductId: $mstTradeProductContent->getMstTradeProductId(),
            contentType: $mstTradeProductContent->getContentType(),
            contentMstId: $mstTradeProductContent->getContentMstId(),
            contentQuantity: $mstTradeProductContent->getContentQuantity(),
            sortOrder: $mstTradeProductContent->getSortOrder(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<MstTradeProductContent>  $mstTradeProductContents
     * @return array<int, TradeProductContent>
     */
    public static function toDtoArray(iterable $mstTradeProductContents): array
    {
        $dtos = [];

        foreach ($mstTradeProductContents as $mstTradeProductContent) {
            $dtos[] = self::toDto($mstTradeProductContent);
        }

        return $dtos;
    }
}
