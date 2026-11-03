<?php

namespace App\Repositories\Trx;

use App\Adapters\Trade\TradeProductCountAdapter;
use NexusTrade\DataTransferObjects\TradeProductCount;
use NexusTrade\Repositories\TradeProductCountRepositoryInterface;

/**
 * TradeProductCountRepositoryAdapter
 *
 * TrxTradeProductRepository（Model）と TradeProductCountRepositoryInterface（DTO）の橋渡し
 */
class TradeProductCountRepositoryAdapter implements TradeProductCountRepositoryInterface
{
    public function __construct(
        private readonly TrxTradeProductRepository $trxTradeProductRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $sysPlayerId): array
    {
        return TradeProductCountAdapter::toDtoArray($this->trxTradeProductRepository->selectAllByPlayer()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectByProductId(int $sysPlayerId, string $mstTradeProductId): ?TradeProductCount
    {
        $trxTradeProduct = $this->trxTradeProductRepository->selectByMstTradeProductId($mstTradeProductId);

        return $trxTradeProduct === null ? null : TradeProductCountAdapter::toDto($trxTradeProduct);
    }

    /**
     * {@inheritDoc}
     */
    public function save(TradeProductCount $tradeProductCount): void
    {
        $this->trxTradeProductRepository->upsertTradeCount(
            $tradeProductCount->getSysPlayerId(),
            $tradeProductCount->getMstTradeProductId(),
            $tradeProductCount->getTradeCount(),
            $tradeProductCount->getLastTradedAt(),
        );
    }
}
