<?php

namespace App\Domain\Gacha\Services;

use NexusGacha\Services\DrawService as BaseDrawService;

/**
 * DrawService
 *
 * パッケージ版のDrawServiceのラッパー
 * 配列形式で結果を返すために変換処理を行う
 */
class DrawService
{
    public function __construct(
        private readonly BaseDrawService $baseDrawService,
    ) {
    }

    /**
     * ガチャを実行して景品リストを取得
     *
     * @param string $mstGachaId
     * @param int $drawCount
     * @param bool $hasStepUp
     * @param int $currentStep
     * @param string|null $selectedCandidateId
     * @return array
     */
    public function draw(
        string $mstGachaId,
        int $drawCount,
        bool $hasStepUp,
        int $currentStep,
        ?string $selectedCandidateId = null
    ): array {
        $prizes = $this->baseDrawService->draw(
            $mstGachaId,
            $drawCount,
            $hasStepUp,
            $currentStep,
            $selectedCandidateId
        );

        // DTOを配列に変換
        return array_map(fn($prize) => $prize->toArray(), $prizes);
    }
}

