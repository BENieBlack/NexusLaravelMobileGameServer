<?php

namespace App\Domain\Player\Services;

use NexusStamina\Services\PlayerLevelServiceInterface;

/**
 * PlayerLevelServiceAdapter
 *
 * 既存のLevelServiceをnexus-staminaのインターフェースに適合させるアダプタ
 */
class PlayerLevelServiceAdapter implements PlayerLevelServiceInterface
{
    public function __construct(
        private readonly PlayerLevelService $levelService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findMaxStamina(int $sysPlayerId): int
    {
        return $this->levelService->findMaxStamina($sysPlayerId);
    }
}
