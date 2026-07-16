<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;

/**
 * ResourceDeliveryHandlerInterface
 * 
 * リソースタイプ別の配送処理を実装するHandlerのインターフェース
 */
interface ResourceDeliveryHandlerInterface
{
    /**
     * 配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryContentDto $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $content): void;

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param ResourceType|string $type リソースタイプ
     * @return bool
     */
    public function supports(ResourceType|string $type): bool;
}
