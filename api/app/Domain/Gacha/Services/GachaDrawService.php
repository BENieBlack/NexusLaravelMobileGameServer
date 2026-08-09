<?php

namespace App\Domain\Gacha\Services;

use NexusGacha\Services\GachaDrawService as PackageGachaDrawService;

/**
 * GachaDrawService
 *
 * パッケージ版のGachaDrawServiceのラッパー
 * 配列形式で結果を返すために変換処理を行う
 */
class GachaDrawService
{
    public function __construct(
        private readonly PackageGachaDrawService $baseDrawService,
    ) {}

    /**
     * ガチャを実行して景品リストを取得
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
        return array_map(fn ($prize) => $prize->toArray(), $prizes);
    }
}
