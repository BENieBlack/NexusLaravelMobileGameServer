<?php

namespace App\Http\Responses\Unit;

use App\Domain\Unit\DataTransferObjects\LevelUpResult;
use App\Http\Responses\_BaseResponse;

/**
 * LevelUpResponse
 *
 * ユニットレベルアップAPIのレスポンス
 *
 * 命名規約:
 * - Bool値: is_* / has_* プレフィックス
 * - 変更前後: before_* / after_*
 */
class LevelUpResponse extends _BaseResponse
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

    /**
     * レベルアップ結果からレスポンスを生成
     */
    public static function fromResult(LevelUpResult $result): self
    {
        return new self(
            isLeveledUp: $result->isLeveledUp,
            beforeLevel: $result->beforeLevel,
            afterLevel: $result->afterLevel,
            totalExp: $result->totalExp,
            expToNext: $result->expToNext,
            rarity: $result->rarity,
            maxLevel: $result->maxLevel,
            itemUsed: $result->itemUsed,
            expGained: $result->expGained,
        );
    }

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'is_leveled_up' => $this->isLeveledUp,
            'before_level' => $this->beforeLevel,
            'after_level' => $this->afterLevel,
            'total_exp' => $this->totalExp,
            'exp_to_next' => $this->expToNext,
            'rarity' => $this->rarity,
            'max_level' => $this->maxLevel,
            'item_used' => $this->itemUsed,
            'exp_gained' => $this->expGained,
        ];
    }
}
