<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * ExperienceDeliveryHandler
 *
 * 経験値配送処理を担当するHandler
 *
 * 経験値は累積値を加算するだけで、Walletのような先入先出の残高管理が要らない。
 * レベルアップ判定も伴うため、付与はExperienceGranterInterfaceの実装
 * （Application層）に委ねる。
 *
 * 付与先はプレイヤーのほかユニット・装備が想定されるため、
 * metadata['target_type'] / metadata['target_id'] で指定できる。
 * 未指定ならプレイヤー経験値として扱う。
 *
 * 対応リソース:
 * - ResourceType::EXPERIENCE
 */
class ExperienceDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly ExperienceGranterInterface $experienceGranter,
    ) {}

    /**
     * 経験値配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        $metadata = $resourceDeliveryContent->getMetadata();
        $targetType = $metadata['target_type'] ?? ExperienceGranterInterface::TARGET_PLAYER;
        $targetId = $metadata['target_id'] ?? null;

        $this->experienceGranter->grantExperience(
            $sysPlayerId,
            $resourceDeliveryContent->getAmount(),
            (string) $targetType,
            $targetId !== null ? (string) $targetId : null
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;

        return $typeValue === ResourceType::EXPERIENCE->value;
    }
}
