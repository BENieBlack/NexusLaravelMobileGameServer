<?php

namespace NexusGacha\DataTransferObjects;

/**
 * RarityRate
 *
 * ガチャのレアリティ排出率を表すDTO
 */
class RarityRate
{
    public function __construct(
        private readonly int $rarity,
        private readonly int $rate,
    ) {}

    public function getRarity(): int
    {
        return $this->rarity;
    }

    public function getRate(): int
    {
        return $this->rate;
    }
}
