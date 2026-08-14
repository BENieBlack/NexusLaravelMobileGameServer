<?php

namespace NexusBilling\Tests\Unit\Services;

use NexusBilling\Services\DiamondBalanceService;
use NexusBilling\Contracts\DiamondRepositoryInterface;
use NexusBilling\DTOs\DiamondBalanceDto;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * DiamondBalanceServiceのユニットテスト
 * 
 * パッケージ層の純粋なビジネスロジックをテスト
 */
class DiamondBalanceServiceTest extends TestCase
{
    private DiamondBalanceService $diamondBalanceService;
    private DiamondRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(DiamondRepositoryInterface::class);
        $this->diamondBalanceService = new DiamondBalanceService($this->mockRepository);
    }

    /**
     * @test
     */
    public function 無償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $addAmount = 200;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existingDto);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalanceDto $dto) use ($freeAmount, $addAmount, $paidAmount) {
                return $dto->getFreeAmount() === ($freeAmount + $addAmount)
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $addAmount, false);

        // Assert
        $this->assertInstanceOf(DiamondBalanceDto::class, $result);
        $this->assertSame($freeAmount + $addAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 有償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $addAmount = 300;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existingDto);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $result = $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $addAmount, true);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount + $addAmount, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 新規プレイヤーに無償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Apple';
        $addAmount = 1000;

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalanceDto $dto) use ($addAmount) {
                return $dto->getFreeAmount() === $addAmount
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $addAmount, false);

        // Assert
        $this->assertSame($addAmount, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 残高を取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existingDto);

        // Act
        $result = $this->diamondBalanceService->findBalance($sysPlayerId, $platform);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($paidAmount, $result['paid_amount']);
        $this->assertSame($freeAmount, $result['free_amount']);
        $this->assertSame($paidAmount + $freeAmount, $result['total_amount']);
    }

    /**
     * @test
     */
    public function 存在しないプレイヤーの残高は0を返す(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Apple';

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        // Act
        $result = $this->diamondBalanceService->findBalance($sysPlayerId, $platform);

        // Assert
        $this->assertSame(0, $result['paid_amount']);
        $this->assertSame(0, $result['free_amount']);
        $this->assertSame(0, $result['total_amount']);
    }

    /**
     * @test
     */
    public function 無償ダイヤのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $consumeAmount = 200;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existingDto]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame($freeAmount - $consumeAmount, $existingDto->getFreeAmount());
        $this->assertSame($paidAmount, $existingDto->getPaidAmount());
    }

    /**
     * @test
     */
    public function 無償を使い切った後に有償を消費する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 1000;
        $consumeAmount = 800; // 無償500 + 有償300を消費

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->willReturn([$existingDto]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $existingDto->getFreeAmount());
        $this->assertSame(700, $existingDto->getPaidAmount()); // 1000 - 300
    }

    /**
     * @test
     */
    public function 有償ダイヤのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $consumeAmount = 200;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existingDto]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame($freeAmount, $existingDto->getFreeAmount());
        $this->assertSame($paidAmount - $consumeAmount, $existingDto->getPaidAmount());
    }

    /**
     * @test
     */
    public function 無償優先消費で残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 300;
        $consumeAmount = 1000; // 合計800より多い

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existingDto]);

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);
    }

    /**
     * @test
     */
    public function 有償ダイヤ消費で残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 200;
        $consumeAmount = 300; // 有償残高より多い

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existingDto]);

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, true);
    }

    /**
     * @test
     */
    public function 新規プレイヤーに有償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Google';
        $addAmount = 500;

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalanceDto $dto) use ($addAmount) {
                return $dto->getPaidAmount() === $addAmount
                    && $dto->getFreeAmount() === 0;
            }));

        // Act
        $result = $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $addAmount, true);

        // Assert
        $this->assertSame($addAmount, $result->getPaidAmount());
        $this->assertSame(0, $result->getFreeAmount());
    }

    /**
     * @test
     */
    public function 複数プラットフォームにまたがる消費ができる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platformApple = 'Apple';
        $platformGoogle = 'Google';
        $consumeAmount = 1200; // Apple 800 + Google 400

        $dtoApple = new DiamondBalanceDto($sysPlayerId, $platformApple, 500, 300); // 合計800
        $dtoGoogle = new DiamondBalanceDto($sysPlayerId, $platformGoogle, 300, 200); // 合計500

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$dtoApple, $dtoGoogle]);

        $this->mockRepository
            ->expects($this->atLeast(2))
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $dtoApple->getFreeAmount());
        $this->assertSame(0, $dtoApple->getPaidAmount());
        $this->assertSame(0, $dtoGoogle->getFreeAmount()); // 200を全て消費
        $this->assertSame(100, $dtoGoogle->getPaidAmount()); // 300 - 200
    }

    /**
     * @test
     */
    public function プラットフォームが存在しない場合の消費で例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $consumeAmount = 100;

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([]); // プラットフォームが存在しない

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);
    }

    /**
     * @test
     */
    public function 全て消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 300;
        $consumeAmount = 800; // ちょうど使い切る

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->willReturn([$existingDto]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $existingDto->getFreeAmount());
        $this->assertSame(0, $existingDto->getPaidAmount());
    }

    /**
     * @test
     */
    public function 有償のみ消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 300;
        $consumeAmount = 300; // 有償をちょうど使い切る

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->willReturn([$existingDto]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame($freeAmount, $existingDto->getFreeAmount());
        $this->assertSame(0, $existingDto->getPaidAmount());
    }

    /**
     * @test
     */
    public function ゼロ加算しても既存残高は変わらない(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;

        $existingDto = new DiamondBalanceDto($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlatform')
            ->willReturn($existingDto);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalanceDto $dto) use ($freeAmount, $paidAmount) {
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, 0, false);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    /**
     * @test
     */
    public function 複数プラットフォームの有償ダイヤのみ消費(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platformApple = 'Apple';
        $platformGoogle = 'Google';
        $consumeAmount = 600; // Apple 500 + Google 100

        $dtoApple = new DiamondBalanceDto($sysPlayerId, $platformApple, 500, 300);
        $dtoGoogle = new DiamondBalanceDto($sysPlayerId, $platformGoogle, 300, 200);

        $this->mockRepository
            ->expects($this->once())
            ->method('findAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$dtoApple, $dtoGoogle]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame(300, $dtoApple->getFreeAmount()); // 無償は変わらない
        $this->assertSame(0, $dtoApple->getPaidAmount()); // 有償500を全て消費
        $this->assertSame(200, $dtoGoogle->getFreeAmount()); // 無償は変わらない
        $this->assertSame(200, $dtoGoogle->getPaidAmount()); // 有償300から100消費
    }
}
