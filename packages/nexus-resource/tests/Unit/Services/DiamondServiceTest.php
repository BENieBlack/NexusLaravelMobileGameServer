<?php

namespace NexusResource\Tests\Unit\Services;

use NexusResource\Services\DiamondService;
use NexusResource\Contracts\DiamondRepositoryInterface;
use NexusResource\DataTransferObjects\DiamondBalance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * DiamondServiceのユニットテスト
 * 
 * パッケージ層の純粋なビジネスロジックをテスト
 */
class DiamondServiceTest extends TestCase
{
    private DiamondService $diamondService;
    private DiamondRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(DiamondRepositoryInterface::class);
        $this->diamondService = new DiamondService($this->mockRepository);
    }

    #[Test]
    public function 無償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $addAmount = 200;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existing);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalance $dto) use ($freeAmount, $addAmount, $paidAmount) {
                return $dto->getFreeAmount() === ($freeAmount + $addAmount)
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->diamondService->addDiamond($sysPlayerId, $platform, $addAmount, false);

        // Assert
        $this->assertInstanceOf(DiamondBalance::class, $result);
        $this->assertSame($freeAmount + $addAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 有償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $addAmount = 300;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existing);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $result = $this->diamondService->addDiamond($sysPlayerId, $platform, $addAmount, true);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount + $addAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 新規プレイヤーに無償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Apple';
        $addAmount = 1000;

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalance $dto) use ($addAmount) {
                return $dto->getFreeAmount() === $addAmount
                    && $dto->getPaidAmount() === 0;
            }));

        // Act
        $result = $this->diamondService->addDiamond($sysPlayerId, $platform, $addAmount, false);

        // Assert
        $this->assertSame($addAmount, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 残高を取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn($existing);

        // Act
        $result = $this->diamondService->findBalance($sysPlayerId, $platform);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($paidAmount, $result['paid_amount']);
        $this->assertSame($freeAmount, $result['free_amount']);
        $this->assertSame($paidAmount + $freeAmount, $result['total_amount']);
    }

    #[Test]
    public function 存在しないプレイヤーの残高は0を返す(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Apple';

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        // Act
        $result = $this->diamondService->findBalance($sysPlayerId, $platform);

        // Assert
        $this->assertSame(0, $result['paid_amount']);
        $this->assertSame(0, $result['free_amount']);
        $this->assertSame(0, $result['total_amount']);
    }

    #[Test]
    public function 無償ダイヤのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $consumeAmount = 200;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existing]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame($freeAmount - $consumeAmount, $existing->getFreeAmount());
        $this->assertSame($paidAmount, $existing->getPaidAmount());
    }

    #[Test]
    public function 無償を使い切った後に有償を消費する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 1000;
        $consumeAmount = 800; // 無償500 + 有償300を消費

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->willReturn([$existing]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $existing->getFreeAmount());
        $this->assertSame(700, $existing->getPaidAmount()); // 1000 - 300
    }

    #[Test]
    public function 有償ダイヤのみを消費できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;
        $consumeAmount = 200;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existing]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame($freeAmount, $existing->getFreeAmount());
        $this->assertSame($paidAmount - $consumeAmount, $existing->getPaidAmount());
    }

    #[Test]
    public function 無償優先消費で残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 300;
        $consumeAmount = 1000; // 合計800より多い

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existing]);

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);
    }

    #[Test]
    public function 有償ダイヤ消費で残高不足の場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 200;
        $consumeAmount = 300; // 有償残高より多い

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$existing]);

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, true);
    }

    #[Test]
    public function 新規プレイヤーに有償ダイヤを加算できる(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $platform = 'Google';
        $addAmount = 500;

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->with($sysPlayerId, $platform)
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalance $dto) use ($addAmount) {
                return $dto->getPaidAmount() === $addAmount
                    && $dto->getFreeAmount() === 0;
            }));

        // Act
        $result = $this->diamondService->addDiamond($sysPlayerId, $platform, $addAmount, true);

        // Assert
        $this->assertSame($addAmount, $result->getPaidAmount());
        $this->assertSame(0, $result->getFreeAmount());
    }

    #[Test]
    public function 複数プラットフォームにまたがる消費ができる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platformApple = 'Apple';
        $platformGoogle = 'Google';
        $consumeAmount = 1200; // Apple 800 + Google 400

        $dtoApple = new DiamondBalance($sysPlayerId, $platformApple, 500, 300); // 合計800
        $dtoGoogle = new DiamondBalance($sysPlayerId, $platformGoogle, 300, 200); // 合計500

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$dtoApple, $dtoGoogle]);

        $this->mockRepository
            ->expects($this->atLeast(2))
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $dtoApple->getFreeAmount());
        $this->assertSame(0, $dtoApple->getPaidAmount());
        $this->assertSame(0, $dtoGoogle->getFreeAmount()); // 200を全て消費
        $this->assertSame(100, $dtoGoogle->getPaidAmount()); // 300 - 200
    }

    #[Test]
    public function プラットフォームが存在しない場合の消費で例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $consumeAmount = 100;

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([]); // プラットフォームが存在しない

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);
    }

    #[Test]
    public function 全て消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 500;
        $paidAmount = 300;
        $consumeAmount = 800; // ちょうど使い切る

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->willReturn([$existing]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, false);

        // Assert
        $this->assertSame(0, $existing->getFreeAmount());
        $this->assertSame(0, $existing->getPaidAmount());
    }

    #[Test]
    public function 有償のみ消費して残高がゼロになる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 300;
        $consumeAmount = 300; // 有償をちょうど使い切る

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->willReturn([$existing]);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame($freeAmount, $existing->getFreeAmount());
        $this->assertSame(0, $existing->getPaidAmount());
    }

    #[Test]
    public function ゼロ加算しても既存残高は変わらない(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platform = 'Apple';
        $freeAmount = 1000;
        $paidAmount = 500;

        $existing = new DiamondBalance($sysPlayerId, $platform, $paidAmount, $freeAmount);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectByPlatform')
            ->willReturn($existing);

        $this->mockRepository
            ->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(function (DiamondBalance $dto) use ($freeAmount, $paidAmount) {
                return $dto->getFreeAmount() === $freeAmount
                    && $dto->getPaidAmount() === $paidAmount;
            }));

        // Act
        $result = $this->diamondService->addDiamond($sysPlayerId, $platform, 0, false);

        // Assert
        $this->assertSame($freeAmount, $result->getFreeAmount());
        $this->assertSame($paidAmount, $result->getPaidAmount());
    }

    #[Test]
    public function 複数プラットフォームの有償ダイヤのみ消費(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $platformApple = 'Apple';
        $platformGoogle = 'Google';
        $consumeAmount = 600; // Apple 500 + Google 100

        $dtoApple = new DiamondBalance($sysPlayerId, $platformApple, 500, 300);
        $dtoGoogle = new DiamondBalance($sysPlayerId, $platformGoogle, 300, 200);

        $this->mockRepository
            ->expects($this->once())
            ->method('selectAllByPlayerId')
            ->with($sysPlayerId)
            ->willReturn([$dtoApple, $dtoGoogle]);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('persistDiamond');

        // Act
        $this->diamondService->consumeDiamond($sysPlayerId, $consumeAmount, true);

        // Assert
        $this->assertSame(300, $dtoApple->getFreeAmount()); // 無償は変わらない
        $this->assertSame(0, $dtoApple->getPaidAmount()); // 有償500を全て消費
        $this->assertSame(200, $dtoGoogle->getFreeAmount()); // 無償は変わらない
        $this->assertSame(200, $dtoGoogle->getPaidAmount()); // 有償300から100消費
    }
}
