<?php

namespace NexusVip\DTOs;

/**
 * VIPレベルアップ報酬DTO
 */
class VipRewardDto
{
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentId,
        private readonly ?array $contentOption,
        private readonly int $contentQuantity,
        private readonly int $amount,
        private readonly bool $isPaid = false,
    ) {
    }

    /**
     * コンテンツタイプを取得
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * コンテンツIDを取得
     */
    public function getContentId(): string
    {
        return $this->contentId;
    }

    /**
     * コンテンツオプションを取得
     */
    public function getContentOption(): ?array
    {
        return $this->contentOption;
    }

    /**
     * コンテンツ数量を取得
     */
    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    /**
     * 量を取得
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 有償かどうかを取得
     */
    public function getIsPaid(): bool
    {
        return $this->isPaid;
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
