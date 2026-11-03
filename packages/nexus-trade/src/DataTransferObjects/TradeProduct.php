<?php

namespace NexusTrade\DataTransferObjects;

/**
 * TradeProduct
 *
 * 商品（mst_trade_product の1行）
 */
class TradeProduct
{
    /**
     * @param  string  $costType  消費種別（アプリが決める。例: diamond / item / wallet）
     * @param  string|null  $costMstId  消費するもののマスターID（種別によっては不要）
     * @param  int  $costAmount  1回の交換で消費する個数
     * @param  int|null  $tradeLimit  交換回数の上限（NULLは無制限）
     * @param  string  $resetType  交換回数を数え直す周期（TradeResetType）
     * @param  string  $startAt  交換開始日時（Y-m-d H:i:s形式）
     * @param  string|null  $endAt  交換終了日時（Y-m-d H:i:s形式、NULLは無期限）
     */
    public function __construct(
        private readonly string $id,
        private readonly string $mstTradeId,
        private readonly string $costType,
        private readonly ?string $costMstId,
        private readonly int $costAmount,
        private readonly ?int $tradeLimit,
        private readonly string $resetType,
        private readonly string $startAt,
        private readonly ?string $endAt,
        private readonly int $sortOrder,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getMstTradeId(): string
    {
        return $this->mstTradeId;
    }

    public function getCostType(): string
    {
        return $this->costType;
    }

    public function getCostMstId(): ?string
    {
        return $this->costMstId;
    }

    public function getCostAmount(): int
    {
        return $this->costAmount;
    }

    public function getTradeLimit(): ?int
    {
        return $this->tradeLimit;
    }

    public function getResetType(): string
    {
        return $this->resetType;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): ?string
    {
        return $this->endAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
