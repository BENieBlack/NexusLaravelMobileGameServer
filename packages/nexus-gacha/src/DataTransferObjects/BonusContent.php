<?php

namespace NexusGacha\DataTransferObjects;

/**
 * BonusContent
 *
 * ボーナス枠の候補コンテンツを表すDTO
 */
class BonusContent
{
    public function __construct(
        private readonly string $id,
        private readonly string $stepBonusId,
        private readonly string $contentType,
        private readonly string $contentMstId,
        /** @var array<string, mixed>|null */
        private readonly ?array $contentOption,
        private readonly int $amount,
        private readonly int $weight,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStepBonusId(): string
    {
        return $this->stepBonusId;
    }

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
