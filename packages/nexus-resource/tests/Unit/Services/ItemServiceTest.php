<?php

namespace NexusResource\Tests\Unit\Services;

use NexusResource\Services\ItemService;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DataTransferObjects\Item;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ItemServiceのユニットテスト
 * 
 * パッケージ層の純粋なビジネスロジックをテスト
 * - Modelに依存しない
 * - DTOのみを使用
 * - Repository InterfaceをMock化
 */
class ItemServiceTest extends TestCase
{
    private ItemService $itemService;
    private ItemRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Repository InterfaceをMock化
        $this->mockRepository = $this->createMock(ItemRepositoryInterface::class);
        
        // ItemServiceをインスタンス化
        $this->itemService = new ItemService($this->mockRepository);
    }

    #[Test]
    public function 新規アイテムを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;

        // findItemはnullを返す（新規）
        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn(null);

        // persistItemが1回呼ばれることを確認
        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($sysPlayerId, $mstItemId, $freeAmount, $paidAmount) {
                return $dto->getSysPlayerId() === $sysPlayerId
                    && $dto->getMstItemId() === $mstItemId
                    && $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->itemService->addItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame($sysPlayerId, $result->getSysPlayerId());
        $this->assertSame($mstItemId, $result->getMstItemId());
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 既存アイテムに加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $existingFree = 50;
        $existingPaid = 20;
        $addFree = 30;
        $addPaid = 10;

        $existingItem = new Item($sysPlayerId, $mstItemId, $existingFree, $existingPaid);

        // findItemは既存のDTOを返す
        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($existingItem);

        // persistItemが1回呼ばれることを確認
        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($existingFree, $existingPaid, $addFree, $addPaid) {
                return $dto->getFreeAmount() === ($existingFree + $addFree)
                    && $dto->getPaidAmount() === ($existingPaid + $addPaid);
            }));

        // Act
        $result = $this->itemService->addItem($sysPlayerId, $mstItemId, $addFree, $addPaid);

        // Assert
        $this->assertSame($existingFree + $addFree, $result->getFreeAmount());
        $this->assertSame($existingPaid + $addPaid, $result->getPaidAmount());
    }

    #[Test]
    public function 有償アイテムのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $consumeAmount = 30;

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($freeAmount, $paidAmount, $consumeAmount) {
                // 有償から優先的に消費されるので、無償は変わらず、有償が減る
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === ($paidAmount - $consumeAmount);
            }));

        // Act
        $result = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount - $consumeAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 有償を使い切った後に無償を消費する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $consumeAmount = 80; // 有償50 + 無償30を消費

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($freeAmount, $consumeAmount, $paidAmount) {
                // 有償50を全て消費、残り30を無償から消費
                return $dto->getFreeAmount() === ($freeAmount - ($consumeAmount - $paidAmount))
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame(70, $result->getFreeAmount()); // 100 - 30
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 存在しないアイテムを消費すると例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_nonexistent';

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn(null);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item not found');

        // Act
        $this->itemService->consumeItem($sysPlayerId, $mstItemId, 10);
    }

    #[Test]
    public function 残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $existingItem = new Item($sysPlayerId, $mstItemId, 10, 5); // 合計15

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient item amount');

        // Act
        $this->itemService->consumeItem($sysPlayerId, $mstItemId, 20); // 15より多い
    }

    #[Test]
    public function 無償と有償を同時に加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $existingFree = 50;
        $existingPaid = 20;
        $addFree = 30;
        $addPaid = 10;

        $existingItem = new Item($sysPlayerId, $mstItemId, $existingFree, $existingPaid);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($existingFree, $existingPaid, $addFree, $addPaid) {
                return $dto->getFreeAmount() === ($existingFree + $addFree)
                    && $dto->getPaidAmount() === ($existingPaid + $addPaid);
            }));

        // Act
        $result = $this->itemService->addItem($sysPlayerId, $mstItemId, $addFree, $addPaid);

        // Assert
        $this->assertSame($existingFree + $addFree, $result->getFreeAmount());
        $this->assertSame($existingPaid + $addPaid, $result->getPaidAmount());
    }

    #[Test]
    public function 有償のみ消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $consumeAmount = 50; // 有償をちょうど使い切る

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($freeAmount) {
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 無償のみ消費できる_有償がゼロの場合(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 0;
        $consumeAmount = 30;

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($freeAmount, $consumeAmount) {
                return $dto->getFreeAmount() === ($freeAmount - $consumeAmount)
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame($freeAmount - $consumeAmount, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 全て消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 50;
        $paidAmount = 30;
        $consumeAmount = 80; // 合計80をちょうど使い切る

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) {
                return $dto->getFreeAmount() === 0
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame(0, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function ゼロ加算しても既存残高は変わらない(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;

        $existingItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn($existingItem);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) use ($freeAmount, $paidAmount) {
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->itemService->addItem($sysPlayerId, $mstItemId, 0, 0);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 新規アイテムをゼロで作成できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_new';

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistItem')
            ->with($this->callback(function (Item $dto) {
                return $dto->getFreeAmount() === 0
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemService->addItem($sysPlayerId, $mstItemId, 0, 0);

        // Assert
        $this->assertSame(0, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function アイテムの所持数を取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $expectedTotal = 150;

        $expectedItem = new Item($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($expectedItem);

        // Act
        $result = $this->itemService->findItemAmount($sysPlayerId, $mstItemId);

        // Assert
        $this->assertSame($expectedTotal, $result);
    }

    #[Test]
    public function 存在しないアイテムは0を返す(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_nonexistent';

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn(null);

        // Act
        $result = $this->itemService->findItemAmount($sysPlayerId, $mstItemId);

        // Assert
        $this->assertSame(0, $result);
    }

    #[Test]
    public function 複数アイテムの所持数を一括取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemIds = ['item_potion_001', 'item_sword_001', 'item_shield_001'];
        
        $items = [
            new Item($sysPlayerId, 'item_potion_001', 100, 50), // 合計150
            new Item($sysPlayerId, 'item_sword_001', 5, 2),     // 合計7
            new Item($sysPlayerId, 'item_shield_001', 3, 1),    // 合計4
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn($items);

        // Act
        $result = $this->itemService->findItemAmounts($sysPlayerId, $mstItemIds);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(150, $result['item_potion_001']);
        $this->assertSame(7, $result['item_sword_001']);
        $this->assertSame(4, $result['item_shield_001']);
    }

    #[Test]
    public function 存在しないアイテムを含む場合は0で補完される(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $mstItemIds = ['item_potion_001', 'item_nonexistent'];
        
        $items = [
            new Item($sysPlayerId, 'item_potion_001', 100, 50), // 合計150
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn($items);

        // Act
        $result = $this->itemService->findItemAmounts($sysPlayerId, $mstItemIds);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(150, $result['item_potion_001']);
        $this->assertSame(0, $result['item_nonexistent']);
    }

    #[Test]
    public function 空配列を渡すと空配列が返る(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemIds = [];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn([]);

        // Act
        $result = $this->itemService->findItemAmounts($sysPlayerId, $mstItemIds);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function 全て存在しないアイテムの場合は全て0で返る(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemIds = ['item_a', 'item_b', 'item_c'];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn([]); // 全て存在しない

        // Act
        $result = $this->itemService->findItemAmounts($sysPlayerId, $mstItemIds);

        // Assert
        $this->assertCount(3, $result);
        $this->assertSame(0, $result['item_a']);
        $this->assertSame(0, $result['item_b']);
        $this->assertSame(0, $result['item_c']);
    }
}
