<?php

namespace NexusVip\DTOs;

/**
 * VIPレベルアップ報酬DTO
 */
class VipRewardDto
{
    public function __construct(
        public readonly string $contentType,
        public readonly string $contentId,
        public readonly ?array $contentOption,
        public readonly int $contentQuantity,
        public readonly int $amount,
        public readonly bool $isPaid = false,
    ) {
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     */
    public function getTotalQuantity(): int
    {
        return $this->contentQuantity * $this->amount;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'content_option' => $this->contentOption,
            'content_quantity' => $this->contentQuantity,
            'amount' => $this->amount,
            'total_quantity' => $this->getTotalQuantity(),
            'is_paid' => $this->isPaid,
        ];
    }
}
