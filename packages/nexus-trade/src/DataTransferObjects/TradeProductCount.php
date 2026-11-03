<?php

namespace NexusTrade\DataTransferObjects;

/**
 * TradeProductCount
 *
 * プレイヤーの商品ごとの交換回数（trx_trade_product の1行）
 *
 * tradeCount は lastTradedAt が属する周期での回数。
 * 周期が変わったかどうかは TradeService が判定する
 */
class TradeProductCount
{
    /**
     * @param  string  $lastTradedAt  最後に交換した日時（Y-m-d H:i:s形式）
     */
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $mstTradeProductId,
        private readonly int $tradeCount,
        private readonly string $lastTradedAt,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getMstTradeProductId(): string
    {
        return $this->mstTradeProductId;
    }

    public function getTradeCount(): int
    {
        return $this->tradeCount;
    }

    public function getLastTradedAt(): string
    {
        return $this->lastTradedAt;
    }
}
