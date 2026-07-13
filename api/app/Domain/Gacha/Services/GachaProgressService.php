<?php

namespace App\Domain\Gacha\Services;

use App\Models\Trx\TrxGacha;
use App\Repositories\Trx\TrxGachaRepository;
use NexusUtilities\ClockUtility;

/**
 * GachaProgressService
 *
 * ガチャの進行状況（リセット、ステップ管理）を行うサービス
 */
class GachaProgressService
{
    public function __construct(
        private readonly TrxGachaRepository $trxGachaRepository,
    ) {
    }

    /**
     * ガチャ進行状況を取得または作成
     *
     * @param int $sysPlayerId
     * @param string $mstGachaId
     * @return TrxGacha
     */
    public function getOrCreateProgress(int $sysPlayerId, string $mstGachaId): TrxGacha
    {
        $progress = TrxGacha::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_delete', false)
            ->first();

        if (!$progress) {
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
     *
     * @param TrxGacha $progress
     * @return TrxGacha
     */
    public function checkAndResetDaily(TrxGacha $progress): TrxGacha
    {
        $now = ClockUtility::now();
        $dailyResetAt = $progress->getAttribute('daily_reset_at');
        $todayString = $now->startOfDay()->toDateString(); // Y-m-d形式

        // daily_reset_atが今日の0時より前ならリセット
        // 文字列比較: daily_reset_atから日付部分を取得して比較
        $dailyResetDate = $dailyResetAt !== null ? substr($dailyResetAt, 0, 10) : null;
        if ($dailyResetDate === null || $dailyResetDate < $todayString) {
            $progress->setAttribute('daily_draw_count', 0);
            $progress->setAttribute('daily_reset_at', $now);
        }

        return $progress;
    }

    /**
     * ガチャ実行後に進行状況を更新
     *
     * @param TrxGacha $progress
     * @param int $drawCount
     * @param int|null $nextStep
     * @return void
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
}
