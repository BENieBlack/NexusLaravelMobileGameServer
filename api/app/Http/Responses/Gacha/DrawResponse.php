<?php

namespace App\Http\Responses\Gacha;

use App\Domain\Gacha\DataTransferObjects\DrawResult;
use App\Http\Responses\_BaseResponse;

/**
 * DrawResponse
 *
 * ガチャ実行APIのレスポンス
 */
class DrawResponse extends _BaseResponse
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

    /**
     * ガチャ実行結果からレスポンスを生成
     */
    public static function fromResult(DrawResult $result): self
    {
        return new self(
            prizes: $result->prizes,
            currentStep: $result->currentStep,
            dailyDrawCount: $result->dailyDrawCount,
            totalDrawCount: $result->totalDrawCount,
            hasNextStep: $result->hasNextStep,
            nextStepInfo: $result->nextStepInfo,
        );
    }

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'prizes' => $this->prizes,
            'current_step' => $this->currentStep,
            'daily_draw_count' => $this->dailyDrawCount,
            'total_draw_count' => $this->totalDrawCount,
            'has_next_step' => $this->hasNextStep,
            'next_step_info' => $this->nextStepInfo,
        ];
    }
}
