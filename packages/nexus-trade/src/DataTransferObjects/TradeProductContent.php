<?php

namespace NexusTrade\DataTransferObjects;

/**
 * TradeProductContent
 *
 * 商品を1回交換すると手に入るもの（mst_trade_product_content の1行）
 */
class TradeProductContent
{
    public function __construct(
        private readonly string $mstTradeProductId,
        private readonly string $contentType,
        private readonly string $contentMstId,
        private readonly int $contentQuantity,
        private readonly int $sortOrder,
    ) {}

    public function getMstTradeProductId(): string
    {
        return $this->mstTradeProductId;
    }

    /**
     * 手に入るものの種別（Nexus\Core\Resource\Enums\ResourceType の値）
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * 1回あたりの数量
     */
    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_mst_id' => $this->contentMstId,
            'content_quantity' => $this->contentQuantity,
        ];
    }
}
