<?php

namespace App\Domain\Item\Services;

use NexusResourceDelivery\Contracts\ItemGranterInterface;

/**
 * ItemGranterAdapter
 *
 * nexus-resource-deliveryのItemGranterInterfaceを実装し、
 * Application層のItemServiceをラップする。
 *
 * ItemService が mst_item.is_wallet を見て trx_item と Wallet を
 * 振り分ける。配送がパッケージ層のItemServiceを直接呼ぶと
 * この振り分けを通らず、Wallet管理のアイテムが trx_item へ入って
 * プレイヤーから見えなくなる。
 */
class ItemGranterAdapter implements ItemGranterInterface
{
    public function __construct(
        private readonly ItemService $itemService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function grantItem(int $sysPlayerId, string $mstItemId, int $amount, ?string $expireAt = null): void
    {
        $this->itemService->addItem($sysPlayerId, $mstItemId, freeAmount: $amount, expireAt: $expireAt);
    }
}
