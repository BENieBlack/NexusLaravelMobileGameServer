<?php

namespace Nexus\Core\ResourceDelivery\Handlers;

use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

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
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void;

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool;
}
