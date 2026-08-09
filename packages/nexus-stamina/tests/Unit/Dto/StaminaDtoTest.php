<?php

namespace NexusStamina\Tests\Unit\Dto;

use NexusStamina\Dto\StaminaDto;
use PHPUnit\Framework\TestCase;

/**
 * StaminaDtoのユニットテスト
 */
class StaminaDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function DTOを正常に作成できる(): void
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
     * @test
     * 現在スタミナを設定できる
     */
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
     * @test
     * 回復速度倍率を設定できる
     */
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
     * @test
     * 最終回復時刻を設定できる
     */
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
     * @test
     * スタミナが最大値に達しているかチェックできる（達している）
     */
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
     * @test
     * スタミナが最大値に達しているかチェックできる（達していない）
     */
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
     * @test
     * 十分なスタミナがあるかチェックできる（十分にある）
     */
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
     * @test
     * 十分なスタミナがあるかチェックできる（不足）
     */
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
     * @test
     * ゼロスタミナで作成できる
     */
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
     * @test
     * 回復速度倍率が2倍の場合
     */
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
