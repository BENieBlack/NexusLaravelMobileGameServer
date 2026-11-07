<?php

namespace App\Repositories\Mst;

use App\Adapters\Trade\TradeAdapter;
use App\Adapters\Trade\TradeProductAdapter;
use App\Adapters\Trade\TradeProductContentAdapter;
use App\Models\Mst\MstTradeProductContent;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
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
        private readonly EventPeriodResolver $eventPeriodResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectAllTrades(): array
    {
        return array_map(
            fn (Trade $trade) => $this->applyEventPeriodToTrade($trade),
            TradeAdapter::toDtoArray($this->mstTradeRepository->queryOrMemory()->values()),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectTradeById(string $mstTradeId): ?Trade
    {
        $mstTrade = $this->mstTradeRepository->selectById($mstTradeId);

        return $mstTrade === null ? null : $this->applyEventPeriodToTrade(TradeAdapter::toDto($mstTrade));
    }

    /**
     * {@inheritDoc}
     */
    public function selectAllProducts(): array
    {
        return array_map(
            fn (TradeProduct $tradeProduct) => $this->applyEventPeriodToProduct($tradeProduct),
            TradeProductAdapter::toDtoArray($this->mstTradeProductRepository->queryOrMemory()->values()),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectProductById(string $mstTradeProductId): ?TradeProduct
    {
        $mstTradeProduct = $this->mstTradeProductRepository->selectById($mstTradeProductId);

        return $mstTradeProduct === null ? null : $this->applyEventPeriodToProduct(TradeProductAdapter::toDto($mstTradeProduct));
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

    /**
     * 交換所がイベントに入っていれば、イベントの期間で上書きする
     *
     * 子の end_at には exchange_end_at が入る（交換所は交換期間まで使える）。
     */
    private function applyEventPeriodToTrade(Trade $trade): Trade
    {
        $period = $this->eventPeriodResolver->resolve(EventContentType::TRADE, $trade->getId());

        return $period === null ? $trade : $trade->withPeriod($period->getStartAt(), $period->getEndAt());
    }

    /**
     * イベントに入っている交換所の商品は、商品自身の期間ではなく、交換所と同じイベントの期間にする
     *
     * 交換所を別のイベントで使い回したとき、商品に古い期間が残って交換できなくなるのを防ぐ。
     */
    private function applyEventPeriodToProduct(TradeProduct $tradeProduct): TradeProduct
    {
        $period = $this->eventPeriodResolver->resolve(EventContentType::TRADE, $tradeProduct->getMstTradeId());

        return $period === null ? $tradeProduct : $tradeProduct->withPeriod($period->getStartAt(), $period->getEndAt());
    }
}
