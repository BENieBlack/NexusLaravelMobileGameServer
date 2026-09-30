<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\NaturalResourceDeliveryHandler;
use NexusWallet\Services\WalletService;
use NexusWallet\ValueObjects\CurrencyOperationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NaturalResourceDeliveryHandler のユニットテスト
 *
 * 加算はWalletServiceの向こう側なので、渡す引数を検証する。
 * 自然資源は全て無償・無期限で入る（有償枠に入れてはいけない）のが要点。
 *
 * 戻り値のCurrencyOperationResultはfinalでモックが自動生成できないため、
 * 呼び出し引数の検証に影響しないダミーを返している。
 */
class NaturalResourceDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function 自然資源のタイプをすべてサポートする(): void
    {
        $handler = new NaturalResourceDeliveryHandler($this->createMock(WalletService::class));

        $types = [
            ResourceType::FOOD,
            ResourceType::WOOD,
            ResourceType::STONE,
            ResourceType::IRON,
        ];

        foreach ($types as $type) {
            $this->assertTrue($handler->supports($type), "{$type->value} をサポートしていない");
            $this->assertTrue($handler->supports($type->value), "{$type->value} をサポートしていない");
        }
    }

    #[Test]
    public function 自然資源以外はサポートしない(): void
    {
        $handler = new NaturalResourceDeliveryHandler($this->createMock(WalletService::class));

        $this->assertFalse($handler->supports(ResourceType::DIAMOND));
        $this->assertFalse($handler->supports(ResourceType::ALLIANCE_POINTS));
        $this->assertFalse($handler->supports('unknown_type'));

        // スタミナと経験値は別Handlerが担当する
        $this->assertFalse($handler->supports(ResourceType::STAMINA));
        $this->assertFalse($handler->supports(ResourceType::EXPERIENCE));
    }

    #[Test]
    public function 数量を無償枠に加算する(): void
    {
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'food', 120, 0, null)
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new NaturalResourceDeliveryHandler($wallet);

        $handler->handle(777, $this->makeContent(ResourceType::FOOD, 'food', 120));
    }

    #[Test]
    public function 有効期限が指定されていても無期限で加算する(): void
    {
        // 自然資源は期限を持たせない仕様なので、コンテンツ側の期限は渡さない
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'wood', 10, 0, null)
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new NaturalResourceDeliveryHandler($wallet);

        $handler->handle(777, $this->makeContent(ResourceType::WOOD, 'wood', 10, '2026-12-31 23:59:59'));
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
