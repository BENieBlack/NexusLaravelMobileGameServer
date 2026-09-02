<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\ItemGranterInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * ItemDeliveryHandler
 *
 * アイテム配送処理を担当するHandler
 * 付与は ItemGranterInterface へ委譲する。trx_item と Wallet の
 * どちらへ入れるかは mst_item.is_wallet で決まり、その判定には
 * マスターの読み取りが要るためApplication層にしか置けない
 *
 * 対応リソース:
 * - ResourceType::ITEM
 * - ResourceType::CONSUMABLE
 * - ResourceType::MATERIAL
 * - ResourceType::TICKET
 * - ResourceType::GACHA_TICKET
 */
class ItemDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly ItemGranterInterface $itemGranter,
    ) {}

    /**
     * アイテム配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // 既存なら加算、無ければ作成。
        // trx_item と Wallet のどちらへ入れるかは実装側が mst_item.is_wallet で決める
        $this->itemGranter->grantItem(
            $sysPlayerId,
            $resourceDeliveryContent->getId(),
            $resourceDeliveryContent->getAmount(),
            $resourceDeliveryContent->getExpireAt()
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

        return in_array($typeValue, [
            ResourceType::ITEM->value,
            ResourceType::CONSUMABLE->value,
            ResourceType::MATERIAL->value,
            ResourceType::TICKET->value,
            ResourceType::GACHA_TICKET->value,
        ]);
    }
}
