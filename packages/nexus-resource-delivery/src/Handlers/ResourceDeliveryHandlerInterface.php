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
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContentDto  $resourceDeliveryContentDto  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContentDto $resourceDeliveryContentDto): void;

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool;
}
