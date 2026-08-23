<?php

namespace App\Domain\Player\Services;

use NexusLevel\Services\PlayerLevelService;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;

/**
 * ExperienceGranterAdapter
 *
 * nexus-resource-deliveryのExperienceGranterInterfaceを実装する。
 *
 * 現状はプレイヤー経験値のみ対応する。ユニット経験値・装備経験値は
 * それぞれUnitLevelService / EquipmentLevelServiceが対象IDを必要とするため、
 * 配送コンテンツ側の指定方法を決めてから追加する。
 */
class ExperienceGranterAdapter implements ExperienceGranterInterface
{
    public function __construct(
        private readonly PlayerLevelService $playerLevelService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function grantExperience(
        int $sysPlayerId,
        int $amount,
        string $targetType = self::TARGET_PLAYER,
        ?string $targetId = null
    ): void {
        if ($targetType !== self::TARGET_PLAYER) {
            throw new \InvalidArgumentException("Unsupported experience target: {$targetType}");
        }

        $this->playerLevelService->addExp($sysPlayerId, $amount);
    }
}
