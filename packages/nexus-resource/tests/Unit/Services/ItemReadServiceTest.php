<?php

namespace NexusResource\Tests\Unit\Services;

use NexusResource\Services\ItemReadService;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DTOs\ItemDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ItemReadServiceのユニットテスト
 * 
 * パッケージ層の純粋なビジネスロジックをテスト
 */
class ItemReadServiceTest extends TestCase
{
    private ItemReadService $itemReadService;
    private ItemRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(ItemRepositoryInterface::class);
        $this->itemReadService = new ItemReadService($this->mockRepository);
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

        $expectedDto = new ItemDto($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($expectedDto);

        // Act
        $result = $this->itemReadService->findItemAmount($sysPlayerId, $mstItemId);

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
        $result = $this->itemReadService->findItemAmount($sysPlayerId, $mstItemId);

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
            new ItemDto($sysPlayerId, 'item_potion_001', 100, 50), // 合計150
            new ItemDto($sysPlayerId, 'item_sword_001', 5, 2),     // 合計7
            new ItemDto($sysPlayerId, 'item_shield_001', 3, 1),    // 合計4
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn($items);

        // Act
        $result = $this->itemReadService->findItemAmounts($sysPlayerId, $mstItemIds);

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
            new ItemDto($sysPlayerId, 'item_potion_001', 100, 50), // 合計150
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('selectItemsByIds')
            ->with($sysPlayerId, $mstItemIds)
            ->willReturn($items);

        // Act
        $result = $this->itemReadService->findItemAmounts($sysPlayerId, $mstItemIds);

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
        $result = $this->itemReadService->findItemAmounts($sysPlayerId, $mstItemIds);

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
        $result = $this->itemReadService->findItemAmounts($sysPlayerId, $mstItemIds);

        // Assert
        $this->assertCount(3, $result);
        $this->assertSame(0, $result['item_a']);
        $this->assertSame(0, $result['item_b']);
        $this->assertSame(0, $result['item_c']);
    }
}
