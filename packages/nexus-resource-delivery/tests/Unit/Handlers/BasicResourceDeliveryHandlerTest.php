<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\BasicResourceDeliveryHandler;
use NexusWallet\Services\WalletService;
use NexusWallet\ValueObjects\CurrencyOperationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BasicResourceDeliveryHandler のユニットテスト
 *
 * 加算はWalletServiceの向こう側なので、渡す引数を検証する。
 * 基本リソースは全て無償・無期限で入る（有償枠に入れてはいけない）のが要点。
 *
 * 戻り値のCurrencyOperationResultはfinalでモックが自動生成できないため、
 * 呼び出し引数の検証に影響しないダミーを返している。
 */
class BasicResourceDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function 基本リソースのタイプをすべてサポートする(): void
    {
        $handler = new BasicResourceDeliveryHandler($this->createMock(WalletService::class));

        $types = [
            ResourceType::FOOD,
            ResourceType::WOOD,
            ResourceType::STONE,
            ResourceType::IRON,
            ResourceType::STAMINA,
            ResourceType::EXPERIENCE,
        ];

        foreach ($types as $type) {
            $this->assertTrue($handler->supports($type), "{$type->value} をサポートしていない");
            $this->assertTrue($handler->supports($type->value), "{$type->value} をサポートしていない");
        }
    }

    #[Test]
    public function 基本リソース以外はサポートしない(): void
    {
        $handler = new BasicResourceDeliveryHandler($this->createMock(WalletService::class));

        $this->assertFalse($handler->supports(ResourceType::DIAMOND));
        $this->assertFalse($handler->supports(ResourceType::ALLIANCE_POINTS));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 数量を無償枠に加算する(): void
    {
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'food', 120, 0, null)
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new BasicResourceDeliveryHandler($wallet);

        $handler->handle(777, $this->makeContent(ResourceType::FOOD, 'food', 120));
    }

    #[Test]
    public function 有効期限が指定されていても無期限で加算する(): void
    {
        // 基本リソースは期限を持たせない仕様なので、コンテンツ側の期限は渡さない
        $wallet = $this->createMock(WalletService::class);
        $wallet->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'wood', 10, 0, null)
            ->willReturn(new CurrencyOperationResult(0, 0, 0));

        $handler = new BasicResourceDeliveryHandler($wallet);

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
