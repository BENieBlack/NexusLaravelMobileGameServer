<?php

namespace NexusGacha\Services;

use NexusGacha\Dto\GachaProgressDto;
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
     * @return GachaProgressDto
     */
    public function getOrCreateProgress(int $sysPlayerId, string $mstGachaId): GachaProgressDto
    {
        $progress = $this->progressRepository->selectByPlayerAndGacha($sysPlayerId, $mstGachaId);

        if (!$progress) {
            $now = ClockUtility::nowToString();
            $progress = new GachaProgressDto(
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
     * @param GachaProgressDto $gachaProgressDto
     * @return GachaProgressDto
     */
    public function checkAndResetDaily(GachaProgressDto $gachaProgressDto): GachaProgressDto
    {
        $now = ClockUtility::now();
        $dailyResetAt = $gachaProgressDto->getDailyResetAt();
        $todayString = $now->startOfDay()->toDateString(); // Y-m-d形式

        // daily_reset_atが今日の0時より前ならリセット
        $dailyResetDate = $dailyResetAt !== null ? substr($dailyResetAt, 0, 10) : null;
        if ($dailyResetDate === null || $dailyResetDate < $todayString) {
            $gachaProgressDto->setDailyDrawCount(0);
            $gachaProgressDto->setDailyResetAt(ClockUtility::nowToString());
        }

        return $gachaProgressDto;
    }

    /**
     * ガチャ実行後に進行状況を更新
     *
     * @param GachaProgressDto $gachaProgressDto
     * @param int $drawCount
     * @param int|null $nextStep
     * @return void
     */
    public function updateProgress(GachaProgressDto $gachaProgressDto, int $drawCount, ?int $nextStep = null): void
    {
        $gachaProgressDto->setDailyDrawCount($gachaProgressDto->getDailyDrawCount() + 1);
        $gachaProgressDto->setTotalDrawCount($gachaProgressDto->getTotalDrawCount() + $drawCount);
        
        if ($nextStep !== null) {
            $gachaProgressDto->setCurrentStep($nextStep);
        }

        $this->progressRepository->persist($gachaProgressDto);
    }
}
