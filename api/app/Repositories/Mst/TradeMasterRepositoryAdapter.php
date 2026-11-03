<?php

namespace App\Repositories\Mst;

use App\Adapters\Trade\TradeAdapter;
use App\Adapters\Trade\TradeProductAdapter;
use App\Adapters\Trade\TradeProductContentAdapter;
use App\Models\Mst\MstTradeProductContent;
use NexusTrade\Contracts\TradeMasterRepositoryInterface;
use NexusTrade\DataTransferObjects\Trade;
use NexusTrade\DataTransferObjects\TradeProduct;

/**
 * TradeMasterRepositoryAdapter
 *
 * MstTrade / MstTradeProduct / MstTradeProductContent の Repository（Model）と
 * TradeMasterRepositoryInterface（DTO）の橋渡し
 */
class TradeMasterRepositoryAdapter implements TradeMasterRepositoryInterface
{
    public function __construct(
        private readonly MstTradeRepository $mstTradeRepository,
        private readonly MstTradeProductRepository $mstTradeProductRepository,
        private readonly MstTradeProductContentRepository $mstTradeProductContentRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllTrades(): array
    {
        return TradeAdapter::toDtoArray($this->mstTradeRepository->queryOrMemory()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectTradeById(string $mstTradeId): ?Trade
    {
        $mstTrade = $this->mstTradeRepository->selectById($mstTradeId);

        return $mstTrade === null ? null : TradeAdapter::toDto($mstTrade);
    }

    /**
     * {@inheritDoc}
     */
    public function selectAllProducts(): array
    {
        return TradeProductAdapter::toDtoArray($this->mstTradeProductRepository->queryOrMemory()->values());
    }

    /**
     * {@inheritDoc}
     */
    public function selectProductById(string $mstTradeProductId): ?TradeProduct
    {
        $mstTradeProduct = $this->mstTradeProductRepository->selectById($mstTradeProductId);

        return $mstTradeProduct === null ? null : TradeProductAdapter::toDto($mstTradeProduct);
    }

    /**
     * {@inheritDoc}
     */
    public function selectContentsByProductId(string $mstTradeProductId): array
    {
        $mstTradeProductContents = $this->mstTradeProductContentRepository->queryOrMemory()
            ->filter(fn (MstTradeProductContent $content) => $content->getMstTradeProductId() === $mstTradeProductId)
            ->sortBy(fn (MstTradeProductContent $content) => $content->getSortOrder())
            ->values();

        return TradeProductContentAdapter::toDtoArray($mstTradeProductContents);
    }
}
