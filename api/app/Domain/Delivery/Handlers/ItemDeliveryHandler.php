<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Item\Services\ItemService;

/**
 * ItemDeliveryHandler
 * 
 * アイテム配送処理を担当するHandler
 * ItemServiceを使用して、複合主キー (sys_player_id, mst_item_id) でアイテムを管理
 */
class ItemDeliveryHandler implements _BaseDeliveryHandlerInterface
{
    public function __construct(
        private readonly ItemService $itemService,
    ) {
    }

    /**
     * アイテム配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // ItemServiceのaddItemメソッドを使用（既存の場合は加算、新規の場合は作成）
        $this->itemService->addItem($sysPlayerId, $content->getId(), $content->getAmount());
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ
     * @return bool
     */
    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_ITEM;
    }
}
