<?php

namespace NexusGacha\DataTransferObjects;

/**
 * StepBonus
 *
 * ステップアップガチャのボーナス枠を表すDTO
 *
 * position=0 はランダム位置に bonus_count 個差し込む枠
 */
class StepBonus
{
    public function __construct(
        private readonly string $id,
        private readonly int $position,
        private readonly int $bonusCount,
        private readonly string $selectionType,
        private readonly ?int $bonusRarity,
        private readonly bool $isPickupOnly,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getBonusCount(): int
    {
        return $this->bonusCount;
    }

    public function getSelectionType(): string
    {
        return $this->selectionType;
    }

    public function getBonusRarity(): ?int
    {
        return $this->bonusRarity;
    }

    public function isPickupOnly(): bool
    {
        return $this->isPickupOnly;
    }
}
