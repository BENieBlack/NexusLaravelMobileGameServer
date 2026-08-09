<?php

namespace App\Http\Responses\Gacha;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DrawResponse
 *
 * ガチャ実行APIのレスポンス
 */
class DrawResponse implements Responsable
{
    /**
     * @param  array  $prizes  獲得した景品リスト
     * @param  int  $currentStep  現在のステップ番号
     * @param  int  $dailyDrawCount  本日の実行回数
     * @param  int  $totalDrawCount  累計実行回数
     * @param  bool  $hasNextStep  次のステップがあるか
     * @param  array|null  $nextStepInfo  次のステップ情報（あれば）
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
     * レスポンスを生成
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'prizes' => $this->prizes,
            'current_step' => $this->currentStep,
            'daily_draw_count' => $this->dailyDrawCount,
            'total_draw_count' => $this->totalDrawCount,
            'has_next_step' => $this->hasNextStep,
            'next_step_info' => $this->nextStepInfo,
        ]);
    }
}
