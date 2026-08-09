<?php

namespace App\Http\Responses\Unit;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LevelUpResponse
 *
 * ユニットレベルアップAPIのレスポンス
 *
 * 命名規約:
 * - Bool値: is_* / has_* プレフィックス
 * - 変更前後: before_* / after_*
 */
class LevelUpResponse implements Responsable
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
     * レスポンスを生成
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'is_leveled_up' => $this->isLeveledUp,
            'before_level' => $this->beforeLevel,
            'after_level' => $this->afterLevel,
            'total_exp' => $this->totalExp,
            'exp_to_next' => $this->expToNext,
            'rarity' => $this->rarity,
            'max_level' => $this->maxLevel,
            'item_used' => $this->itemUsed,
            'exp_gained' => $this->expGained,
        ]);
    }
}
