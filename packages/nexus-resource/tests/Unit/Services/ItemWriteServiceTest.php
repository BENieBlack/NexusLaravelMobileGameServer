<?php

namespace NexusResource\Tests\Unit\Services;

use NexusResource\Services\ItemWriteService;
use NexusResource\Contracts\ItemRepositoryInterface;
use NexusResource\DTOs\ItemDto;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ItemWriteServiceのユニットテスト
 * 
 * パッケージ層の純粋なビジネスロジックをテスト
 * - Modelに依存しない
 * - DTOのみを使用
 * - Repository InterfaceをMock化
 */
class ItemWriteServiceTest extends TestCase
{
    private ItemWriteService $itemWriteService;
    private ItemRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Repository InterfaceをMock化
        $this->mockRepository = $this->createMock(ItemRepositoryInterface::class);
        
        // ItemWriteServiceをインスタンス化
        $this->itemWriteService = new ItemWriteService($this->mockRepository);
    }

    /**
     * @test
     */
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
            ->method('findItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn(null);

        // saveItemが1回呼ばれることを確認
        $this->mockRepository
            ->expects($this->once())
            ->method('saveItem')
            ->with($this->callback(function (ItemDto $dto) use ($sysPlayerId, $mstItemId, $freeAmount, $paidAmount) {
                return $dto->getSysPlayerId() === $sysPlayerId
                    && $dto->getMstItemId() === $mstItemId
                    && $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->itemWriteService->addItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        // Assert
        $this->assertInstanceOf(ItemDto::class, $result);
        $this->assertSame($sysPlayerId, $result->getSysPlayerId());
        $this->assertSame($mstItemId, $result->getMstItemId());
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 既存アイテムに加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $existingFree = 50;
        $existingPaid = 20;
        $addFree = 30;
        $addPaid = 10;

        $existingDto = new ItemDto($sysPlayerId, $mstItemId, $existingFree, $existingPaid);

        // findItemは既存のDTOを返す
        $this->mockRepository
            ->expects($this->once())
            ->method('findItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($existingDto);

        // saveItemが1回呼ばれることを確認
        $this->mockRepository
            ->expects($this->once())
            ->method('saveItem')
            ->with($this->callback(function (ItemDto $dto) use ($existingFree, $existingPaid, $addFree, $addPaid) {
                return $dto->getFreeAmount() === ($existingFree + $addFree)
                    && $dto->getPaidAmount() === ($existingPaid + $addPaid);
            }));

        // Act
        $result = $this->itemWriteService->addItem($sysPlayerId, $mstItemId, $addFree, $addPaid);

        // Assert
        $this->assertSame($existingFree + $addFree, $result->getFreeAmount());
        $this->assertSame($existingPaid + $addPaid, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 有償アイテムのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $consumeAmount = 30;

        $existingDto = new ItemDto($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findItem')
            ->with($sysPlayerId, $mstItemId)
            ->willReturn($existingDto);

        $this->mockRepository
            ->expects($this->once())
            ->method('saveItem')
            ->with($this->callback(function (ItemDto $dto) use ($freeAmount, $paidAmount, $consumeAmount) {
                // 有償から優先的に消費されるので、無償は変わらず、有償が減る
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === ($paidAmount - $consumeAmount);
            }));

        // Act
        $result = $this->itemWriteService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount - $consumeAmount, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 有償を使い切った後に無償を消費する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $freeAmount = 100;
        $paidAmount = 50;
        $consumeAmount = 80; // 有償50 + 無償30を消費

        $existingDto = new ItemDto($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findItem')
            ->willReturn($existingDto);

        $this->mockRepository
            ->expects($this->once())
            ->method('saveItem')
            ->with($this->callback(function (ItemDto $dto) use ($freeAmount, $consumeAmount, $paidAmount) {
                // 有償50を全て消費、残り30を無償から消費
                return $dto->getFreeAmount() === ($freeAmount - ($consumeAmount - $paidAmount))
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->itemWriteService->consumeItem($sysPlayerId, $mstItemId, $consumeAmount);

        // Assert
        $this->assertSame(70, $result->getFreeAmount()); // 100 - 30
        $this->assertSame(0, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 存在しないアイテムを消費すると例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_nonexistent';

        $this->mockRepository
            ->expects($this->once())
            ->method('findItem')
            ->willReturn(null);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item not found');

        // Act
        $this->itemWriteService->consumeItem($sysPlayerId, $mstItemId, 10);
    }

    /**
     * @test
     */
    public function 残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $mstItemId = 'item_potion_001';
        $existingDto = new ItemDto($sysPlayerId, $mstItemId, 10, 5); // 合計15

        $this->mockRepository
            ->expects($this->once())
            ->method('findItem')
            ->willReturn($existingDto);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient item amount');

        // Act
        $this->itemWriteService->consumeItem($sysPlayerId, $mstItemId, 20); // 15より多い
    }
}
