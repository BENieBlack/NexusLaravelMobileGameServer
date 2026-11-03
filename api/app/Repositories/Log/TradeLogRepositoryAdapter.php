<?php

namespace App\Repositories\Log;

use NexusTrade\Repositories\TradeLogRepositoryInterface;

/**
 * TradeLogRepositoryAdapter
 *
 * LogTradeRepository（Model）と TradeLogRepositoryInterface の橋渡し
 */
class TradeLogRepositoryAdapter implements TradeLogRepositoryInterface
{
    public function __construct(
        private readonly LogTradeRepository $logTradeRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function insert(
        int $sysPlayerId,
        string $mstTradeProductId,
        int $quantity,
        string $costType,
        ?string $costMstId,
        int $costAmount,
    ): void {
        $this->logTradeRepository->insertTrade($sysPlayerId, $mstTradeProductId, $quantity, $costType, $costMstId, $costAmount);
    }
}
