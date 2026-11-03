<?php

namespace NexusTrade\Repositories;

use NexusTrade\DataTransferObjects\TradeProductCount;

/**
 * TradeProductCountRepositoryInterface
 *
 * 商品ごとの交換回数（trx_trade_product）
 */
interface TradeProductCountRepositoryInterface
{
    /**
     * @return array<int, TradeProductCount>
     */
    public function selectByPlayerId(int $sysPlayerId): array;

    public function selectByProductId(int $sysPlayerId, string $mstTradeProductId): ?TradeProductCount;

    /**
     * 交換回数を保存する（無ければ作る）
     */
    public function save(TradeProductCount $tradeProductCount): void;
}
