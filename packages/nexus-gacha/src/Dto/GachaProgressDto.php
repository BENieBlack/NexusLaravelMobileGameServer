<?php

namespace NexusGacha\Dto;

/**
 * GachaProgressDto
 * 
 * ガチャの進行状況を表すDTO
 */
class GachaProgressDto
{
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $mstGachaId,
        private int $currentStep,
        private int $dailyDrawCount,
        private string $dailyResetAt,
        private int $totalDrawCount,
        private string $totalResetAt,
    ) {
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getMstGachaId(): string
    {
        return $this->mstGachaId;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function setCurrentStep(int $currentStep): void
    {
        $this->currentStep = $currentStep;
    }

    public function getDailyDrawCount(): int
    {
        return $this->dailyDrawCount;
    }

    public function setDailyDrawCount(int $dailyDrawCount): void
    {
        $this->dailyDrawCount = $dailyDrawCount;
    }

    public function getDailyResetAt(): string
    {
        return $this->dailyResetAt;
    }

    public function setDailyResetAt(string $dailyResetAt): void
    {
        $this->dailyResetAt = $dailyResetAt;
    }

    public function getTotalDrawCount(): int
    {
        return $this->totalDrawCount;
    }

    public function setTotalDrawCount(int $totalDrawCount): void
    {
        $this->totalDrawCount = $totalDrawCount;
    }

    public function getTotalResetAt(): string
    {
        return $this->totalResetAt;
    }

    public function setTotalResetAt(string $totalResetAt): void
    {
        $this->totalResetAt = $totalResetAt;
    }
}
