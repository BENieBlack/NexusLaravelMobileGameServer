<?php

namespace App\Domain\Gacha\DataTransferObjects;

/**
 * DrawResult
 *
 * ガチャ実行の結果
 */
class DrawResult
{
    /**
     * @param  array<int, array<string, mixed>>  $prizes  獲得した景品リスト
     * @param  int  $currentStep  現在のステップ番号
     * @param  int  $dailyDrawCount  本日の実行回数
     * @param  int  $totalDrawCount  累計実行回数
     * @param  bool  $hasNextStep  次のステップがあるか
     * @param  array{step_number: int, draw_count: mixed}|null  $nextStepInfo  次のステップ情報（あれば）
     */
    public function __construct(
        public readonly array $prizes,
        public readonly int $currentStep,
        public readonly int $dailyDrawCount,
        public readonly int $totalDrawCount,
        public readonly bool $hasNextStep,
        public readonly ?array $nextStepInfo = null,
    ) {}
}
