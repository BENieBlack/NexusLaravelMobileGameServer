<?php

namespace App\Domain\Stamina\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use NexusResourceDelivery\Contracts\StaminaGranterInterface;

/**
 * StaminaGranterAdapter
 *
 * nexus-resource-deliveryのStaminaGranterInterfaceを実装し、
 * Application層のStaminaServiceをラップする。
 *
 * 配送によるスタミナ付与は、アイテムでの回復と同じ扱い
 * （最大値を超過してよい）にする。
 */
class StaminaGranterAdapter implements StaminaGranterInterface
{
    public function __construct(
        private readonly StaminaService $staminaService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function grantStamina(int $sysPlayerId, int $amount, ?string $staminaType = null): void
    {
        $this->staminaService->recoverStaminaByItem(
            $sysPlayerId,
            $amount,
            $staminaType ?? StaminaConst::TYPE_NORMAL
        );
    }
}
