<?php

namespace NexusTrade\DataTransferObjects;

/**
 * Trade
 *
 * 交換所（mst_trade の1行）。通常交換所やイベント交換所など、商品を並べる場所
 */
class Trade
{
    /**
     * @param  string  $startAt  開始日時（Y-m-d H:i:s形式）
     * @param  string|null  $endAt  終了日時（Y-m-d H:i:s形式、NULLは無期限）
     */
    public function __construct(
        private readonly string $id,
        private readonly string $startAt,
        private readonly ?string $endAt,
        private readonly int $sortOrder,
    ) {}

    public function getId(): string
    {
        return $this->id;
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
