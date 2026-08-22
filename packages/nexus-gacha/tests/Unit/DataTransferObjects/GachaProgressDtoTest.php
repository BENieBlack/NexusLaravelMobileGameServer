<?php

namespace NexusGacha\Tests\Unit\DataTransferObjects;

use NexusGacha\DataTransferObjects\GachaProgress;
use PHPUnit\Framework\TestCase;

class GachaProgressDtoTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 5,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 50,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $this->assertSame(100, $dto->getSysPlayerId());
        $this->assertSame('gacha_001', $dto->getMstGachaId());
        $this->assertSame(1, $dto->getCurrentStep());
        $this->assertSame(5, $dto->getDailyDrawCount());
        $this->assertSame('2026-01-02 00:00:00', $dto->getDailyResetAt());
        $this->assertSame(50, $dto->getTotalDrawCount());
        $this->assertSame('2026-02-01 00:00:00', $dto->getTotalResetAt());
    }

    public function test_set_current_step_updates_value(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 0,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 0,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $this->assertSame(1, $dto->getCurrentStep());
        
        $dto->setCurrentStep(2);
        
        $this->assertSame(2, $dto->getCurrentStep());
    }

    public function test_set_daily_draw_count_updates_value(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 5,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 0,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $this->assertSame(5, $dto->getDailyDrawCount());
        
        $dto->setDailyDrawCount(10);
        
        $this->assertSame(10, $dto->getDailyDrawCount());
    }

    public function test_set_daily_reset_at_updates_value(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 0,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 0,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $newDate = '2026-01-03 00:00:00';
        $dto->setDailyResetAt($newDate);
        
        $this->assertSame($newDate, $dto->getDailyResetAt());
    }

    public function test_set_total_draw_count_updates_value(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 0,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 50,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $this->assertSame(50, $dto->getTotalDrawCount());
        
        $dto->setTotalDrawCount(100);
        
        $this->assertSame(100, $dto->getTotalDrawCount());
    }

    public function test_set_total_reset_at_updates_value(): void
    {
        $dto = new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: 0,
            dailyResetAt: '2026-01-02 00:00:00',
            totalDrawCount: 0,
            totalResetAt: '2026-02-01 00:00:00'
        );

        $newDate = '2026-03-01 00:00:00';
        $dto->setTotalResetAt($newDate);
        
        $this->assertSame($newDate, $dto->getTotalResetAt());
    }
}
