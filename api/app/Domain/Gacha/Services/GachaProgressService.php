<?php

namespace App\Domain\Gacha\Services;

use App\Models\Trx\TrxGacha;
use App\Repositories\Trx\TrxGachaRepository;
use Nexus\Core\Utilities\ClockUtility;
use NexusGacha\DataTransferObjects\GachaProgress;
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
    public function findOrInsertProgress(int $sysPlayerId, string $mstGachaId): TrxGacha
    {
        // Eloquent Modelに変換
        $trxGacha = TrxGacha::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_delete', false)
            ->first();

        if (! $trxGacha) {
            $now = ClockUtility::now();
            $trxGacha = new TrxGacha([
                'sys_player_id' => $sysPlayerId,
                'mst_gacha_id' => $mstGachaId,
                'current_step' => 1,
                'daily_draw_count' => 0,
                'daily_reset_at' => $now,
                'total_draw_count' => 0,
                'total_reset_at' => $now,
            ]);
            $trxGacha->exists = false;
        }

        return $trxGacha;
    }

    /**
     * 日次リセットが必要かチェックし、必要ならリセット
     */
    public function checkAndResetDaily(TrxGacha $trxGacha): TrxGacha
    {
        $progress = $this->convertToDto($trxGacha);
        $updatedProgress = $this->baseProgressService->checkAndResetDaily($progress);

        // 更新された値をModelに反映
        $trxGacha->setAttribute('daily_draw_count', $updatedProgress->getDailyDrawCount());
        $trxGacha->setAttribute('daily_reset_at', $updatedProgress->getDailyResetAt());

        return $trxGacha;
    }

    /**
     * ガチャ実行後に進行状況を更新
     */
    public function updateProgress(TrxGacha $trxGacha, int $drawCount, ?int $nextStep = null): void
    {
        $trxGacha->setAttribute('daily_draw_count', $trxGacha->getDailyDrawCount() + 1);
        $trxGacha->setAttribute('total_draw_count', $trxGacha->getTotalDrawCount() + $drawCount);

        if ($nextStep !== null) {
            $trxGacha->setAttribute('current_step', $nextStep);
        }

        $this->trxGachaRepository->setModel($trxGacha);
    }

    /**
     * TrxGachaモデルをDTOに変換
     */
    private function convertToDto(TrxGacha $trxGacha): GachaProgress
    {
        return new GachaProgress(
            sysPlayerId: $trxGacha->sys_player_id,
            mstGachaId: $trxGacha->mst_gacha_id,
            currentStep: $trxGacha->current_step,
            dailyDrawCount: $trxGacha->daily_draw_count,
            dailyResetAt: $trxGacha->daily_reset_at,
            totalDrawCount: $trxGacha->total_draw_count,
            totalResetAt: $trxGacha->total_reset_at
        );
    }
}
