<?php

namespace NexusGacha\Dto;

/**
 * GachaPrizeDto
 * 
 * ガチャで獲得した景品を表すDTO
 */
class GachaPrizeDto
{
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentId,
        private readonly int $amount,
        private readonly int $rarity,
        private readonly bool $isGuaranteed,
    ) {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentId(): string
    {
        return $this->contentId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getRarity(): int
    {
        return $this->rarity;
    }

    public function isGuaranteed(): bool
    {
        return $this->isGuaranteed;
    }

    /**
     * 配列形式に変換
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'amount' => $this->amount,
            'rarity' => $this->rarity,
            'is_guaranteed' => $this->isGuaranteed,
        ];
    }
}
