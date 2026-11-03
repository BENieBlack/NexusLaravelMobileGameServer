<?php

namespace NexusTrade\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusTrade\Constants\TradeResetType;
use NexusTrade\Contracts\TradeCostPayerInterface;
use NexusTrade\Contracts\TradeMasterRepositoryInterface;
use NexusTrade\DataTransferObjects\Trade;
use NexusTrade\DataTransferObjects\TradeProduct;
use NexusTrade\DataTransferObjects\TradeProductContent;
use NexusTrade\DataTransferObjects\TradeProductCount;
use NexusTrade\Exceptions\TradeException;
use NexusTrade\Repositories\TradeLogRepositoryInterface;
use NexusTrade\Repositories\TradeProductCountRepositoryInterface;
use NexusTrade\ValueObjects\TradeProductStatus;

/**
 * TradeService
 *
 * 交換所のビジネスロジック
 *
 * 責務:
 * - 交換できるの商品と、今の周期での交換回数を返す
 * - コストを支払わせ、手に入るものを配り、交換回数を進める
 *
 * 交換回数は「最後に交換した日時」とその周期での回数だけを持つ。
 * 周期（日・週・月）が変わったら、行を書き換えずに0回とみなす。
 */
class TradeService
{
    public function __construct(
        private readonly TradeMasterRepositoryInterface $masterRepository,
        private readonly TradeProductCountRepositoryInterface $tradeProductCountRepository,
        private readonly TradeLogRepositoryInterface $tradeLogRepository,
        private readonly TradeCostPayerInterface $costPayer,
        private readonly ResourceDeliveryService $deliveryService,
    ) {}

    /**
     * 交換できるの商品の状態を一覧で返す
     *
     * 交換所の表示順 → 商品の表示順に並べる。開催中の交換所にある、交換期間内の商品だけを返す
     *
     * @return array<int, TradeProductStatus>
     */
    public function findStatuses(int $sysPlayerId): array
    {
        $openTrades = [];
        foreach ($this->masterRepository->selectAllTrades() as $trade) {
            if ($this->isTradeOpen($trade)) {
                $openTrades[$trade->getId()] = $trade;
            }
        }

        $openProducts = array_values(array_filter(
            $this->masterRepository->selectAllProducts(),
            fn (TradeProduct $tradeProduct) => isset($openTrades[$tradeProduct->getMstTradeId()])
                && $this->isProductOpen($tradeProduct),
        ));
        usort($openProducts, function (TradeProduct $a, TradeProduct $b) use ($openTrades) {
            $tradeA = $openTrades[$a->getMstTradeId()];
            $tradeB = $openTrades[$b->getMstTradeId()];

            return [$tradeA->getSortOrder(), $tradeA->getId(), $a->getSortOrder(), $a->getId()]
                <=> [$tradeB->getSortOrder(), $tradeB->getId(), $b->getSortOrder(), $b->getId()];
        });

        $countByProductId = [];
        foreach ($this->tradeProductCountRepository->selectByPlayerId($sysPlayerId) as $tradeProductCount) {
            $countByProductId[$tradeProductCount->getMstTradeProductId()] = $tradeProductCount;
        }

        return array_map(
            fn (TradeProduct $tradeProduct) => new TradeProductStatus(
                tradeProduct: $tradeProduct,
                tradedCount: $this->findTradedCount($tradeProduct, $countByProductId[$tradeProduct->getId()] ?? null),
                tradeProductContents: $this->masterRepository->selectContentsByProductId($tradeProduct->getId()),
            ),
            $openProducts,
        );
    }

    /**
     * 商品を交換する
     *
     * コスト（コスト × 個数）を支払い、手に入るもの（数量 × 個数）を配る
     *
     * @param  int  $quantity  交換する個数
     * @return TradeProductStatus 交換した後の状態
     *
     * @throws TradeException 個数が不正・存在しない・交換期間外・交換回数の上限を超える場合
     */
    public function trade(int $sysPlayerId, string $mstTradeProductId, int $quantity): TradeProductStatus
    {
        if ($quantity < 1) {
            throw TradeException::invalidQuantity($quantity);
        }

        $tradeProduct = $this->masterRepository->selectProductById($mstTradeProductId);
        if ($tradeProduct === null) {
            throw TradeException::productNotFound($mstTradeProductId);
        }

        $trade = $this->masterRepository->selectTradeById($tradeProduct->getMstTradeId());
        if ($trade === null || ! $this->isTradeOpen($trade) || ! $this->isProductOpen($tradeProduct)) {
            throw TradeException::productNotOpen($mstTradeProductId);
        }

        $tradedCount = $this->findTradedCount(
            $tradeProduct,
            $this->tradeProductCountRepository->selectByProductId($sysPlayerId, $mstTradeProductId),
        );

        $tradeLimit = $tradeProduct->getTradeLimit();
        if ($tradeLimit !== null && $tradedCount + $quantity > $tradeLimit) {
            throw TradeException::tradeLimitExceeded($mstTradeProductId, $tradeLimit, $tradedCount, $quantity);
        }

        $costAmount = $tradeProduct->getCostAmount() * $quantity;
        $this->costPayer->pay($sysPlayerId, $tradeProduct->getCostType(), $tradeProduct->getCostMstId(), $costAmount);

        $tradeProductContents = $this->masterRepository->selectContentsByProductId($mstTradeProductId);
        $this->deliver($sysPlayerId, $tradeProductContents, $quantity);

        $this->tradeProductCountRepository->save(new TradeProductCount(
            sysPlayerId: $sysPlayerId,
            mstTradeProductId: $mstTradeProductId,
            tradeCount: $tradedCount + $quantity,
            lastTradedAt: ClockUtility::nowToString(),
        ));

        $this->tradeLogRepository->insert(
            sysPlayerId: $sysPlayerId,
            mstTradeProductId: $mstTradeProductId,
            quantity: $quantity,
            costType: $tradeProduct->getCostType(),
            costMstId: $tradeProduct->getCostMstId(),
            costAmount: $costAmount,
        );

        return new TradeProductStatus($tradeProduct, $tradedCount + $quantity, $tradeProductContents);
    }

    /**
     * 手に入るものを配る
     *
     * @param  array<int, TradeProductContent>  $tradeProductContents
     */
    private function deliver(int $sysPlayerId, array $tradeProductContents, int $quantity): void
    {
        if ($tradeProductContents === []) {
            return;
        }

        // ResourceDeliveryService は「積んでから配る」形
        $this->deliveryService->addContents(array_map(
            fn (TradeProductContent $tradeProductContent) => ResourceDeliveryContent::fromArray([
                'type' => $tradeProductContent->getContentType(),
                'id' => $tradeProductContent->getContentMstId(),
                'amount' => $tradeProductContent->getContentQuantity() * $quantity,
            ]),
            $tradeProductContents,
        ));
        $this->deliveryService->deliver($sysPlayerId);
    }

    /**
     * 今の周期での交換回数（前回の交換が前の周期なら0）
     */
    private function findTradedCount(TradeProduct $tradeProduct, ?TradeProductCount $tradeProductCount): int
    {
        if ($tradeProductCount === null) {
            return 0;
        }

        return $this->isSamePeriod($tradeProduct->getResetType(), $tradeProductCount->getLastTradedAt(), ClockUtility::nowToString())
            ? $tradeProductCount->getTradeCount()
            : 0;
    }

    /**
     * 2つの日時が、交換回数を数え直す周期の同じ区切りに入るか
     *
     * 境界はゲーム内日付で切り替える（日付が変わる時刻は config('nexus-core.day_start_time')）
     */
    private function isSamePeriod(string $resetType, string $dateTime1, string $dateTime2): bool
    {
        return match ($resetType) {
            TradeResetType::DAILY => ClockUtility::isSameGameDay($dateTime1, $dateTime2),
            TradeResetType::WEEKLY => $this->weekKey($dateTime1) === $this->weekKey($dateTime2),
            TradeResetType::MONTHLY => $this->monthKey($dateTime1) === $this->monthKey($dateTime2),
            default => true,
        };
    }

    /**
     * ゲーム内日付で見た週（ISO 8601の週年と週番号）
     */
    private function weekKey(string $dateTime): string
    {
        $gameDay = ClockUtility::calcGameDayStart($dateTime)->format('Y-m-d H:i:s');

        return ClockUtility::isoWeekYear($gameDay).'-'.ClockUtility::weekOfYear($gameDay);
    }

    /**
     * ゲーム内日付で見た月
     */
    private function monthKey(string $dateTime): string
    {
        return ClockUtility::calcGameDayStart($dateTime)->format('Y-m');
    }

    private function isTradeOpen(Trade $trade): bool
    {
        return ClockUtility::isWithin($trade->getStartAt(), $trade->getEndAt());
    }

    private function isProductOpen(TradeProduct $tradeProduct): bool
    {
        return ClockUtility::isWithin($tradeProduct->getStartAt(), $tradeProduct->getEndAt());
    }
}
