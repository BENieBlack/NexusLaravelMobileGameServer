<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\PointsDeliveryHandler;
use NexusWallet\Services\WalletService;
use NexusWallet\ValueObjects\CurrencyOperationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PointsDeliveryHandler のユニットテスト
 *
 * ポイント系は無償枠に入れる点は基本リソースと同じだが、
 * イベントポイント等で期限を持たせられるところが違う。
 *
 * 戻り値のCurrencyOperationResultはfinalでモックが自動生成できないため、
 * 呼び出し引数の検証に影響しないダミーを返している。
 */
class PointsDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function ポイント系のタイプをすべてサポートする(): void
    {
        $handler = new PointsDeliveryHandler($this->createMock(WalletService::class));

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
        $handler = new PointsDeliveryHandler($this->createMock(WalletService::class));

        $this->assertFalse($handler->supports(ResourceType::FOOD));
        $this->assertFalse($handler->supports(ResourceType::DIAMOND));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 数量を無償枠に加算する(): void
    {
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'pvp_points', 50, 0, null)
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new PointsDeliveryHandler($wallet);

        $handler->handle(777, $this->makeContent(ResourceType::PVP_POINTS, 'pvp_points', 50));
    }

    #[Test]
    public function 有効期限はそのまま引き渡す(): void
    {
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'event_points', 300, 0, '2026-12-31 23:59:59')
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new PointsDeliveryHandler($wallet);

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
