<?php

namespace App\Domain\Unit\DataTransferObjects;

/**
 * LevelUpResult
 *
 * ユニットレベルアップの結果
 */
class LevelUpResult
{
    public function __construct(
        public readonly bool $isLeveledUp,
        public readonly int $beforeLevel,
        public readonly int $afterLevel,
        public readonly int $totalExp,
        public readonly ?int $expToNext,
        public readonly string $rarity,
        public readonly int $maxLevel,
        public readonly int $itemUsed,
        public readonly int $expGained,
    ) {}
}
