<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use App\Domain\Item\Services\ItemService;

/**
 * ItemDeliveryHandler
 * 
 * アイテム配送処理を担当するHandler
 * ItemServiceを使用して、複合主キー (sys_player_id, mst_item_id) でアイテムを管理
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
        private readonly ItemService $itemService,
    ) {
    }

    /**
     * アイテム配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryContentDto $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContentDto $content): void
    {
        // ItemServiceのaddItemメソッドを使用（既存の場合は加算、新規の場合は作成）
        $this->itemService->addItem(
            $sysPlayerId,
            $content->getId(),
            $content->getAmount()
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param ResourceType|string $type リソースタイプ
     * @return bool
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
