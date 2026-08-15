<?php

namespace NexusStamina\Tests\Unit\Dto;

use NexusStamina\Dto\StaminaDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StaminaDtoのユニットテスト
 */
class StaminaDtoTest extends TestCase
{
    /**
     * DTOを正常に作成できる
     */
    #[Test]
    public function dt_oを正常に作成できる(): void
    {
        // Act
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getSysPlayerId());
        $this->assertSame('normal', $dto->getType());
        $this->assertSame(100, $dto->getCurrentStamina());
        $this->assertSame(1.0, $dto->getRecoveryRateMultiplier());
        $this->assertSame('2024-01-01 00:00:00', $dto->getLastRecoveryAt());
    }

    /**
     * 現在スタミナを設定できる
     */
    #[Test]
    public function 現在スタミナを設定できる(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setCurrentStamina(50);

        // Assert
        $this->assertSame(50, $dto->getCurrentStamina());
    }

    /**
     * 回復速度倍率を設定できる
     */
    #[Test]
    public function 回復速度倍率を設定できる(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setRecoveryRateMultiplier(2.0);

        // Assert
        $this->assertSame(2.0, $dto->getRecoveryRateMultiplier());
    }

    /**
     * 最終回復時刻を設定できる
     */
    #[Test]
    public function 最終回復時刻を設定できる(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setLastRecoveryAt('2024-01-01 12:00:00');

        // Assert
        $this->assertSame('2024-01-01 12:00:00', $dto->getLastRecoveryAt());
    }

    /**
     * スタミナが最大値に達しているかチェックできる（達している）
     */
    #[Test]
    public function スタミナが最大値に達しているかチェックできる_達している(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act & Assert
        $this->assertTrue($dto->isCurrentStaminaFull(100));
        $this->assertTrue($dto->isCurrentStaminaFull(99)); // 超過している場合もtrue
    }

    /**
     * スタミナが最大値に達しているかチェックできる（達していない）
     */
    #[Test]
    public function スタミナが最大値に達しているかチェックできる_達していない(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act & Assert
        $this->assertFalse($dto->isCurrentStaminaFull(100));
    }

    /**
     * 十分なスタミナがあるかチェックできる（十分にある）
     */
    #[Test]
    public function 十分なスタミナがあるかチェックできる_十分にある(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act & Assert
        $this->assertTrue($dto->hasEnoughStamina(50));
        $this->assertTrue($dto->hasEnoughStamina(100)); // 同じ値でもtrue
    }

    /**
     * 十分なスタミナがあるかチェックできる（不足）
     */
    #[Test]
    public function 十分なスタミナがあるかチェックできる_不足(): void
    {
        // Arrange
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Act & Assert
        $this->assertFalse($dto->hasEnoughStamina(51));
        $this->assertFalse($dto->hasEnoughStamina(100));
    }

    /**
     * ゼロスタミナで作成できる
     */
    #[Test]
    public function ゼロスタミナで作成できる(): void
    {
        // Act
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'normal',
            currentStamina: 0,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(0, $dto->getCurrentStamina());
        $this->assertFalse($dto->hasEnoughStamina(1));
        $this->assertFalse($dto->isCurrentStaminaFull(100));
    }

    /**
     * 回復速度倍率が2倍の場合
     */
    #[Test]
    public function 回復速度倍率が2倍の場合(): void
    {
        // Act
        $dto = new StaminaDto(
            sysPlayerId: 1,
            type: 'event',
            currentStamina: 50,
            recoveryRateMultiplier: 2.0,
            lastRecoveryAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(2.0, $dto->getRecoveryRateMultiplier());
    }
}
