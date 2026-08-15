<?php

namespace NexusGacha\Services;

use NexusGacha\DataTransferObjects\GachaProgress;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;
use Nexus\Core\Utilities\ClockUtility;

/**
 * GachaProgressService
 * 
 * ガチャの進行状況（リセット、ステップ管理）を行うサービス
 */
class GachaProgressService
{
    public function __construct(
        private readonly GachaProgressRepositoryInterface $progressRepository,
    ) {
    }

    /**
     * ガチャ進行状況を取得または作成
     *
     * @param int $sysPlayerId
     * @param string $mstGachaId
     * @return GachaProgress
     */
    public function findOrInsertProgress(int $sysPlayerId, string $mstGachaId): GachaProgress
    {
        $progress = $this->progressRepository->selectByPlayerAndGacha($sysPlayerId, $mstGachaId);

        if (!$progress) {
            $now = ClockUtility::nowToString();
            $progress = new GachaProgress(
                sysPlayerId: $sysPlayerId,
                mstGachaId: $mstGachaId,
                currentStep: 1,
                dailyDrawCount: 0,
                dailyResetAt: $now,
                totalDrawCount: 0,
                totalResetAt: $now
            );
            $progress = $this->progressRepository->insert($progress);
        }

        return $progress;
    }

    /**
     * 日次リセットが必要かチェックし、必要ならリセット
     *
     * @param GachaProgress $gachaProgress
     * @return GachaProgress
     */
    public function checkAndResetDaily(GachaProgress $gachaProgress): GachaProgress
    {
        $now = ClockUtility::now();
        $dailyResetAt = $gachaProgress->getDailyResetAt();
        $todayString = $now->startOfDay()->toDateString(); // Y-m-d形式

        // daily_reset_atが今日の0時より前ならリセット
        $dailyResetDate = $dailyResetAt !== null ? substr($dailyResetAt, 0, 10) : null;
        if ($dailyResetDate === null || $dailyResetDate < $todayString) {
            $gachaProgress->setDailyDrawCount(0);
            $gachaProgress->setDailyResetAt(ClockUtility::nowToString());
        }

        return $gachaProgress;
    }

    /**
     * ガチャ実行後に進行状況を更新
     *
     * @param GachaProgress $gachaProgress
     * @param int $drawCount
     * @param int|null $nextStep
     * @return void
     */
    public function updateProgress(GachaProgress $gachaProgress, int $drawCount, ?int $nextStep = null): void
    {
        $gachaProgress->setDailyDrawCount($gachaProgress->getDailyDrawCount() + 1);
        $gachaProgress->setTotalDrawCount($gachaProgress->getTotalDrawCount() + $drawCount);
        
        if ($nextStep !== null) {
            $gachaProgress->setCurrentStep($nextStep);
        }

        $this->progressRepository->persist($gachaProgress);
    }
}
