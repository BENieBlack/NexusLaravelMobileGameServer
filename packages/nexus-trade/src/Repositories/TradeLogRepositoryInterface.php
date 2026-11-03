<?php

namespace NexusTrade\Repositories;

/**
 * TradeLogRepositoryInterface
 *
 * 商品を交換した記録（log_action_trade）
 */
interface TradeLogRepositoryInterface
{
    /**
     * @param  int  $quantity  交換した個数
     * @param  int  $costAmount  支払った合計（コスト × 個数）
     */
    public function insert(
        int $sysPlayerId,
        string $mstTradeProductId,
        int $quantity,
        string $costType,
        ?string $costMstId,
        int $costAmount,
    ): void;
}
