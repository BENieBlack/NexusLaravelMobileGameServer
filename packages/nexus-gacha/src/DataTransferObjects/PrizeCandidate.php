<?php

namespace NexusGacha\DataTransferObjects;

/**
 * PrizeCandidate
 *
 * ガチャの通常景品（抽選候補）を表すDTO
 *
 * weight は同じレアリティ内での重み
 */
class PrizeCandidate
{
    public function __construct(
        private readonly string $contentType,
        private readonly string $contentMstId,
        /** @var array<string, mixed>|null */
        private readonly ?array $contentOption,
        private readonly int $amount,
        private readonly int $weight,
    ) {}

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentOption(): ?array
    {
        return $this->contentOption;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }
}
