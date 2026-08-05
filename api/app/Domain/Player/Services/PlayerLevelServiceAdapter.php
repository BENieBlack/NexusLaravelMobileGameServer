<?php

namespace App\Domain\Player\Services;

use NexusStamina\Services\PlayerLevelServiceInterface;
use App\Domain\Player\Services\PlayerLevelService;

/**
 * PlayerLevelServiceAdapter
 * 
 * 既存のLevelServiceをnexus-staminaのインターフェースに適合させるアダプタ
 */
class PlayerLevelServiceAdapter implements PlayerLevelServiceInterface
{
    public function __construct(
        private readonly PlayerLevelService $levelService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxStamina(int $sysPlayerId): int
    {
        return $this->levelService->getMaxStamina($sysPlayerId);
    }
}
