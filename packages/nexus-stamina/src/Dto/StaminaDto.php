<?php

namespace NexusStamina\Dto;

/**
 * StaminaDto
 *
 * スタミナ情報のDTO
 */
class StaminaDto
{
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $type,
        private int $currentStamina,
        private float $recoveryRateMultiplier,
        private string $lastRecoveryAt,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCurrentStamina(): int
    {
        return $this->currentStamina;
    }

    public function setCurrentStamina(int $currentStamina): void
    {
        $this->currentStamina = $currentStamina;
    }

    public function getRecoveryRateMultiplier(): float
    {
        return $this->recoveryRateMultiplier;
    }

    public function setRecoveryRateMultiplier(float $recoveryRateMultiplier): void
    {
        $this->recoveryRateMultiplier = $recoveryRateMultiplier;
    }

    public function getLastRecoveryAt(): string
    {
        return $this->lastRecoveryAt;
    }

    public function setLastRecoveryAt(string $lastRecoveryAt): void
    {
        $this->lastRecoveryAt = $lastRecoveryAt;
    }

    /**
     * 最大スタミナに達しているかチェック
     */
    public function isCurrentStaminaFull(int $maxStamina): bool
    {
        return $this->currentStamina >= $maxStamina;
    }

    /**
     * 指定量のスタミナがあるかチェック
     */
    public function hasEnoughStamina(int $amount): bool
    {
        return $this->currentStamina >= $amount;
    }
}
