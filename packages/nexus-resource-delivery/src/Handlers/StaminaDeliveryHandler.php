<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\StaminaGranterInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * StaminaDeliveryHandler
 *
 * スタミナ配送処理を担当するHandler
 *
 * スタミナは残高だけでなく次の回復時刻とセットで管理されるため、
 * 他の数値リソースのようにWalletへ加算できない。
 * 付与はStaminaGranterInterfaceの実装（Application層）に委ねる。
 *
 * 対応リソース:
 * - ResourceType::STAMINA
 */
class StaminaDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly StaminaGranterInterface $staminaGranter,
    ) {}

    /**
     * スタミナ配送処理を実行
     *
     * スタミナ種別は metadata['stamina_type'] で指定できる（未指定なら通常スタミナ）。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        $metadata = $resourceDeliveryContent->getMetadata();
        $staminaType = $metadata['stamina_type'] ?? null;

        $this->staminaGranter->grantStamina(
            $sysPlayerId,
            $resourceDeliveryContent->getAmount(),
            $staminaType !== null ? (string) $staminaType : null
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

        return $typeValue === ResourceType::STAMINA->value;
    }
}
