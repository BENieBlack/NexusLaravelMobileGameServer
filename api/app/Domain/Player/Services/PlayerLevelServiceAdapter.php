<?php

namespace App\Domain\Player\Services;

use NexusPlayer\Services\PlayerLevelService;
use NexusStamina\Services\PlayerLevelServiceInterface;

/**
 * PlayerLevelServiceAdapter
 *
 * nexus-playerのPlayerLevelServiceをnexus-staminaのインターフェースに適合させるアダプタ
 *
 * パッケージ同士を直接依存させないため、橋渡しはApplication層に置く。
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
