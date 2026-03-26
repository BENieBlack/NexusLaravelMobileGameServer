<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\DTOs\DeliveryContent;

/**
 * _BaseDeliveryHandlerInterface
 * 
 * リソースタイプ別の配送処理を実装するHandlerのインターフェース
 */
interface _BaseDeliveryHandlerInterface
{
    /**
     * 配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void;

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ (item, unit, equipment, diamond, wallet)
     * @return bool
     */
    public function supports(string $type): bool;
}
