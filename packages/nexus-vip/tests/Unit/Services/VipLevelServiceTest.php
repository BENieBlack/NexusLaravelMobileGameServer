<?php

namespace NexusVip\Tests\Unit\Services;

use Mockery;
use Nexus\Core\Support\CustomCollection;
use NexusVip\Exceptions\VipLevelNotFoundException;
use NexusVip\Models\MstVipLevel;
use NexusVip\Repositories\VipLevelRepositoryInterface;
use NexusVip\Services\VipLevelService;
use NexusVip\ValueObjects\VipBenefit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VipLevelServiceのユニットテスト
 */
class VipLevelServiceTest extends TestCase
{
    private VipLevelRepositoryInterface $vipLevelRepository;

    private VipLevelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vipLevelRepository = Mockery::mock(VipLevelRepositoryInterface::class);
        $this->service = new VipLevelService($this->vipLevelRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 累積ポイントからVIPレベルを計算できる
     */
    #[Test]
    public function 累積ポイントから_vi_pレベルを計算できる(): void
    {
        // Arrange
        $totalPoints = 1000;
        $mockVipLevel = Mockery::mock(MstVipLevel::class);
        $mockVipLevel->shouldReceive('getLevel')->andReturn(5);

        $this->vipLevelRepository
            ->shouldReceive('selectMaxLevelByPoints')
            ->with($totalPoints)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->calculateLevel($totalPoints);

        // Assert
        $this->assertSame(5, $result);
    }

    /**
     * 次のレベルまでの必要ポイントを取得できる
     */
    #[Test]
    public function 次のレベルまでの必要ポイントを取得できる(): void
    {
        // Arrange
        $currentLevel = 3;
        $currentPoint = 500;

        $mockNextLevel = Mockery::mock(MstVipLevel::class);
        $mockNextLevel->shouldReceive('getRequiredPoint')->andReturn(1000);

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with(4)
            ->once()
            ->andReturn($mockNextLevel);

        // Act
        $result = $this->service->calcPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertSame(500, $result); // 1000 - 500
    }

    /**
     * 最高レベルの場合はnullを返す
     */
    #[Test]
    public function 最高レベルの場合はnullを返す(): void
    {
        // Arrange
        $currentLevel = 10;
        $currentPoint = 10000;

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with(11)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->calcPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertNull($result);
    }

    /**
     * すでに次レベルのポイントに達している場合は0を返す
     */
    #[Test]
    public function すでに次レベルのポイントに達している場合は0を返す(): void
    {
        // Arrange
        $currentLevel = 3;
        $currentPoint = 1500;

        $mockNextLevel = Mockery::mock(MstVipLevel::class);
        $mockNextLevel->shouldReceive('getRequiredPoint')->andReturn(1000);

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with(4)
            ->once()
            ->andReturn($mockNextLevel);

        // Act
        $result = $this->service->calcPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertSame(0, $result); // max(0, 1000 - 1500)
    }

    /**
     * VIPレベルの特典情報を取得できる
     */
    #[Test]
    public function vi_pレベルの特典情報を取得できる(): void
    {
        // Arrange
        $level = 5;

        $mockVipLevel = Mockery::mock(MstVipLevel::class);
        $mockVipLevel->shouldReceive('getMaxStaminaBonus')->andReturn(50);
        $mockVipLevel->shouldReceive('calcDailyDiamondBonus')->andReturn(10);
        $mockVipLevel->shouldReceive('getShopDiscountRate')->andReturn(0.1);
        $mockVipLevel->shouldReceive('getGachaDiscountRate')->andReturn(0.05);

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with($level)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->findBenefits($level);

        // Assert
        $this->assertInstanceOf(VipBenefit::class, $result);
        $this->assertSame(50, $result->getMaxStaminaBonus());
        $this->assertSame(10, $result->calcDailyDiamondBonus());
        $this->assertSame(0.1, $result->getShopDiscountRate());
        $this->assertSame(0.05, $result->getGachaDiscountRate());
    }

    /**
     * 存在しないVIPレベルの特典取得は例外が発生する
     */
    #[Test]
    public function 存在しない_vi_pレベルの特典取得は例外が発生する(): void
    {
        // Arrange
        $level = 999;

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with($level)
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(VipLevelNotFoundException::class);
        $this->expectExceptionMessage('VIP level 999 not found');

        // Act
        $this->service->findBenefits($level);
    }

    /**
     * VIPレベルマスターデータを取得できる
     */
    #[Test]
    public function vi_pレベルマスターデータを取得できる(): void
    {
        // Arrange
        $level = 3;
        $mockVipLevel = Mockery::mock(MstVipLevel::class);

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with($level)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->findVipLevelMaster($level);

        // Assert
        $this->assertSame($mockVipLevel, $result);
    }

    /**
     * 存在しないVIPレベルマスターデータ取得は例外が発生する
     */
    #[Test]
    public function 存在しない_vi_pレベルマスターデータ取得は例外が発生する(): void
    {
        // Arrange
        $level = 999;

        $this->vipLevelRepository
            ->shouldReceive('selectByLevel')
            ->with($level)
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(VipLevelNotFoundException::class);
        $this->expectExceptionMessage('VIP level 999 not found');

        // Act
        $this->service->findVipLevelMaster($level);
    }

    /**
     * 全VIPレベルのリストを取得できる
     */
    #[Test]
    public function 全_vi_pレベルのリストを取得できる(): void
    {
        // Arrange
        $mockLevel1 = Mockery::mock(MstVipLevel::class);
        $mockLevel1->shouldReceive('getLevel')->andReturn(1);
        $mockLevel1->shouldReceive('getRequiredPoint')->andReturn(100);
        $mockLevel1->shouldReceive('getMaxStaminaBonus')->andReturn(10);
        $mockLevel1->shouldReceive('calcDailyDiamondBonus')->andReturn(5);
        $mockLevel1->shouldReceive('getShopDiscountRate')->andReturn(0.05);
        $mockLevel1->shouldReceive('getGachaDiscountRate')->andReturn(0.02);
        $mockLevel1->shouldReceive('getDisplayBadgeUrl')->andReturn('https://example.com/badge1.png');

        $mockLevel2 = Mockery::mock(MstVipLevel::class);
        $mockLevel2->shouldReceive('getLevel')->andReturn(2);
        $mockLevel2->shouldReceive('getRequiredPoint')->andReturn(500);
        $mockLevel2->shouldReceive('getMaxStaminaBonus')->andReturn(20);
        $mockLevel2->shouldReceive('calcDailyDiamondBonus')->andReturn(10);
        $mockLevel2->shouldReceive('getShopDiscountRate')->andReturn(0.1);
        $mockLevel2->shouldReceive('getGachaDiscountRate')->andReturn(0.05);
        $mockLevel2->shouldReceive('getDisplayBadgeUrl')->andReturn('https://example.com/badge2.png');

        $collection = new CustomCollection([$mockLevel1, $mockLevel2]);

        $this->vipLevelRepository
            ->shouldReceive('selectAllLevels')
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->findAllLevels();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['level']);
        $this->assertSame(100, $result[0]['required_point']);
        $this->assertSame(2, $result[1]['level']);
        $this->assertSame(500, $result[1]['required_point']);
    }
}
