<?php

namespace App\Domain\Gacha\Services;

use App\Models\Trx\TrxGacha;
use App\Repositories\Trx\TrxGachaRepository;
use Nexus\Core\Utilities\ClockUtility;
use NexusGacha\Dto\GachaProgressDto;
use NexusGacha\Services\GachaProgressService as PackageGachaProgressService;

/**
 * GachaProgressService
 *
 * パッケージ版のGachaProgressServiceのラッパー
 * Eloquent Modelを返すために変換処理を行う
 */
class GachaProgressService
{
    public function __construct(
        private readonly TrxGachaRepository $trxGachaRepository,
        private readonly PackageGachaProgressService $baseProgressService,
    ) {}

    /**
     * ガチャ進行状況を取得または作成
     */
    public function getOrCreateProgress(int $sysPlayerId, string $mstGachaId): TrxGacha
    {
        // Eloquent Modelに変換
        $progress = TrxGacha::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_delete', false)
            ->first();

        if (! $progress) {
            $now = ClockUtility::now();
            $progress = new TrxGacha([
                'sys_player_id' => $sysPlayerId,
                'mst_gacha_id' => $mstGachaId,
                'current_step' => 1,
                'daily_draw_count' => 0,
                'daily_reset_at' => $now,
                'total_draw_count' => 0,
                'total_reset_at' => $now,
            ]);
            $progress->exists = false;
        }

        return $progress;
    }

    /**
     * 日次リセットが必要かチェックし、必要ならリセット
     */
    public function checkAndResetDaily(TrxGacha $progress): TrxGacha
    {
        $progressDto = $this->convertToDto($progress);
        $updatedDto = $this->baseProgressService->checkAndResetDaily($progressDto);

        // 更新された値をModelに反映
        $progress->setAttribute('daily_draw_count', $updatedDto->getDailyDrawCount());
        $progress->setAttribute('daily_reset_at', $updatedDto->getDailyResetAt());

        return $progress;
    }

    /**
     * ガチャ実行後に進行状況を更新
     */
    public function updateProgress(TrxGacha $progress, int $drawCount, ?int $nextStep = null): void
    {
        $progress->setAttribute('daily_draw_count', $progress->getDailyDrawCount() + 1);
        $progress->setAttribute('total_draw_count', $progress->getTotalDrawCount() + $drawCount);

        if ($nextStep !== null) {
            $progress->setAttribute('current_step', $nextStep);
        }

        $this->trxGachaRepository->setModel($progress);
    }

    /**
     * TrxGachaモデルをDTOに変換
     */
    private function convertToDto(TrxGacha $progress): GachaProgressDto
    {
        return new GachaProgressDto(
            sysPlayerId: $progress->sys_player_id,
            mstGachaId: $progress->mst_gacha_id,
            currentStep: $progress->current_step,
            dailyDrawCount: $progress->daily_draw_count,
            dailyResetAt: $progress->daily_reset_at,
            totalDrawCount: $progress->total_draw_count,
            totalResetAt: $progress->total_reset_at
        );
    }
}
