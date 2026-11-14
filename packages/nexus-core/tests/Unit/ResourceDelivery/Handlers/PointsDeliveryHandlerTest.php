<?php

namespace Nexus\Core\Tests\Unit\ResourceDelivery\Handlers;

use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Handlers\PointsDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PointsDeliveryHandler のユニットテスト
 *
 * ポイント系は無償枠に入れる点は基本リソースと同じだが、
 * イベントポイント等で期限を持たせられるところが違う。
 */
class PointsDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function ポイント系のタイプをすべてサポートする(): void
    {
        $handler = new PointsDeliveryHandler($this->createMock(CurrencyGranterInterface::class));

        $types = [
            ResourceType::ALLIANCE_POINTS,
            ResourceType::PVP_POINTS,
            ResourceType::EVENT_POINTS,
            ResourceType::ACHIEVEMENT_POINTS,
            ResourceType::VIP_POINTS,
        ];

        foreach ($types as $type) {
            $this->assertTrue($handler->supports($type), "{$type->value} をサポートしていない");
            $this->assertTrue($handler->supports($type->value), "{$type->value} をサポートしていない");
        }
    }

    #[Test]
    public function ポイント以外はサポートしない(): void
    {
        $handler = new PointsDeliveryHandler($this->createMock(CurrencyGranterInterface::class));

        $this->assertFalse($handler->supports(ResourceType::FOOD));
        $this->assertFalse($handler->supports(ResourceType::PAID_DIAMOND));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 数量を無償枠に加算する(): void
    {
        $granter = $this->createMock(CurrencyGranterInterface::class);
        $granter->expects($this->once())
            ->method('grantCurrency')
            ->with(777, 'pvp_points', 50, 0, null);

        $handler = new PointsDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(ResourceType::PVP_POINTS, 'pvp_points', 50));
    }

    #[Test]
    public function 有効期限はそのまま引き渡す(): void
    {
        $granter = $this->createMock(CurrencyGranterInterface::class);
        $granter->expects($this->once())
            ->method('grantCurrency')
            ->with(777, 'event_points', 300, 0, '2026-12-31 23:59:59');

        $handler = new PointsDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(
            ResourceType::EVENT_POINTS,
            'event_points',
            300,
            '2026-12-31 23:59:59'
        ));
    }

    private function makeContent(
        ResourceType $type,
        string $id,
        int $amount,
        ?string $expireAt = null
    ): ResourceDeliveryContent {
        return new ResourceDeliveryContent(
            new Resource($type, $id, $amount, $expireAt)
        );
    }
}
