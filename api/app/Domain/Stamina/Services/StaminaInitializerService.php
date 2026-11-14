<?php

namespace App\Domain\Stamina\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Domain\Stamina\Contracts\StaminaInitializerInterface;

/**
 * StaminaInitializerService
 *
 * 他コンテキスト向けの公開 API。Stamina の Service に橋渡しする。
 */
class StaminaInitializerService implements StaminaInitializerInterface
{
    public function __construct(
        private readonly StaminaService $staminaService,
    ) {}

    public function initializeNormalStamina(int $sysPlayerId, int $initialStamina): void
    {
        $this->staminaService->initializeStamina($sysPlayerId, $initialStamina, StaminaConst::TYPE_NORMAL);
    }
}
