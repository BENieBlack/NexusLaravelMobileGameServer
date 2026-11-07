<?php

namespace App\Domain\Unit\Responses;

use App\Domain\_BaseResponse;
use App\Domain\ReturnsUpdatedResources;
use App\Traits\HasUpdatedResources;

/**
 * LevelUpResponse
 *
 * ユニットレベルアップAPIのレスポンス
 *
 * 命名規約:
 * - Bool値: is_* / has_* プレフィックス
 * - 変更前後: before_* / after_*
 */
class LevelUpResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use HasUpdatedResources;

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
            // この処理で更新された trx（trx_unit_list、trx_item_list など）
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
