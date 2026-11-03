<?php

namespace NexusTrade\ValueObjects;

use NexusTrade\DataTransferObjects\TradeProduct;
use NexusTrade\DataTransferObjects\TradeProductContent;

/**
 * TradeProductStatus
 *
 * プレイヤーから見た商品1件の状態（定義・今の周期での交換回数・手に入るもの）
 */
final class TradeProductStatus
{
    /**
     * @param  int  $tradedCount  今の周期での交換回数
     * @param  array<int, TradeProductContent>  $tradeProductContents  1回交換すると手に入るもの
     */
    public function __construct(
        private readonly TradeProduct $tradeProduct,
        private readonly int $tradedCount,
        private readonly array $tradeProductContents,
    ) {}

    public function getTradeProduct(): TradeProduct
    {
        return $this->tradeProduct;
    }

    public function getTradedCount(): int
    {
        return $this->tradedCount;
    }

    /**
     * @return array<int, TradeProductContent>
     */
    public function getTradeProductContents(): array
    {
        return $this->tradeProductContents;
    }

    /**
     * あと何回交換できるか（無制限ならnull）
     */
    public function getRemainingCount(): ?int
    {
        $tradeLimit = $this->tradeProduct->getTradeLimit();

        return $tradeLimit === null ? null : max(0, $tradeLimit - $this->tradedCount);
    }

    public function isSoldOut(): bool
    {
        return $this->getRemainingCount() === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mst_trade_product_id' => $this->tradeProduct->getId(),
            'mst_trade_id' => $this->tradeProduct->getMstTradeId(),
            'cost_type' => $this->tradeProduct->getCostType(),
            'cost_mst_id' => $this->tradeProduct->getCostMstId(),
            'cost_amount' => $this->tradeProduct->getCostAmount(),
            'trade_limit' => $this->tradeProduct->getTradeLimit(),
            'traded_count' => $this->tradedCount,
            'remaining_count' => $this->getRemainingCount(),
            'reset_type' => $this->tradeProduct->getResetType(),
            'end_at' => $this->tradeProduct->getEndAt(),
            'content_list' => array_map(
                fn (TradeProductContent $tradeProductContent) => $tradeProductContent->toArray(),
                $this->tradeProductContents,
            ),
        ];
    }
}
