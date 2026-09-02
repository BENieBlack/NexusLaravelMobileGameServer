<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\ItemGranterInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\ItemDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ItemDeliveryHandler のユニットテスト
 *
 * 付与そのものは実装側（Application層）に委ねる。ここが
 * パッケージ層のItemServiceを直接呼ぶと mst_item.is_wallet の
 * 振り分けを通らず、残高として持つアイテムが trx_item へ入って
 * プレイヤーから見えなくなる。
 *
 * このHandlerの責務は、配送コンテンツを付与の引数へ組み替えるところまで。
 */
class ItemDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function アイテム系のタイプをすべてサポートする(): void
    {
        $handler = new ItemDeliveryHandler($this->createMock(ItemGranterInterface::class));

        $types = [
            ResourceType::ITEM,
            ResourceType::CONSUMABLE,
            ResourceType::MATERIAL,
            ResourceType::TICKET,
            ResourceType::GACHA_TICKET,
        ];

        foreach ($types as $type) {
            $this->assertTrue($handler->supports($type), "{$type->value} をサポートしていない");
            $this->assertTrue($handler->supports($type->value), "{$type->value} をサポートしていない");
        }
    }

    #[Test]
    public function アイテム以外はサポートしない(): void
    {
        $handler = new ItemDeliveryHandler($this->createMock(ItemGranterInterface::class));

        $this->assertFalse($handler->supports(ResourceType::GOLD));
        $this->assertFalse($handler->supports(ResourceType::UNIT));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 数量をそのまま付与へ渡す(): void
    {
        $granter = $this->createMock(ItemGranterInterface::class);
        $granter->expects($this->once())
            ->method('grantItem')
            ->with(777, 'item_potion_001', 3, null);

        $handler = new ItemDeliveryHandler($granter);

        $handler->handle(777, new ResourceDeliveryContent(Resource::item('item_potion_001', 3)));
    }

    #[Test]
    public function 有効期限もそのまま引き渡す(): void
    {
        // Wallet管理のアイテムに配送されたとき、期限を落とすと無期限になる
        $granter = $this->createMock(ItemGranterInterface::class);
        $granter->expects($this->once())
            ->method('grantItem')
            ->with(777, 'gold', 100, '2026-12-31 23:59:59');

        $handler = new ItemDeliveryHandler($granter);

        $handler->handle(777, new ResourceDeliveryContent(
            new Resource(ResourceType::ITEM, 'gold', 100, '2026-12-31 23:59:59')
        ));
    }
}
