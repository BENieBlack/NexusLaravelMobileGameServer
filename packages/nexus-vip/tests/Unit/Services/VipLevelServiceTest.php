<?php

namespace NexusVip\Tests\Unit\Services;

use NexusVip\DTOs\VipBenefitDto;
use NexusVip\Exceptions\VipLevelNotFoundException;
use NexusVip\Models\MstVipLevel;
use NexusVip\Repositories\VipLevelRepositoryInterface;
use NexusVip\Services\VipLevelService;
use NexusPersistence\Support\CustomCollection;
use PHPUnit\Framework\TestCase;
use Mockery;

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
     * @test
     * 累積ポイントからVIPレベルを計算できる
     */
    public function 累積ポイントからVIPレベルを計算できる(): void
    {
        // Arrange
        $totalPoints = 1000;
        $mockVipLevel = Mockery::mock(MstVipLevel::class);
        $mockVipLevel->shouldReceive('getLevel')->andReturn(5);

        $this->vipLevelRepository
            ->shouldReceive('findMaxLevelByPoints')
            ->with($totalPoints)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->calculateLevel($totalPoints);

        // Assert
        $this->assertSame(5, $result);
    }

    /**
     * @test
     * 次のレベルまでの必要ポイントを取得できる
     */
    public function 次のレベルまでの必要ポイントを取得できる(): void
    {
        // Arrange
        $currentLevel = 3;
        $currentPoint = 500;

        $mockNextLevel = Mockery::mock(MstVipLevel::class);
        $mockNextLevel->shouldReceive('getRequiredPoint')->andReturn(1000);

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with(4)
            ->once()
            ->andReturn($mockNextLevel);

        // Act
        $result = $this->service->getPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertSame(500, $result); // 1000 - 500
    }

    /**
     * @test
     * 最高レベルの場合はnullを返す
     */
    public function 最高レベルの場合はnullを返す(): void
    {
        // Arrange
        $currentLevel = 10;
        $currentPoint = 10000;

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with(11)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->getPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     * すでに次レベルのポイントに達している場合は0を返す
     */
    public function すでに次レベルのポイントに達している場合は0を返す(): void
    {
        // Arrange
        $currentLevel = 3;
        $currentPoint = 1500;

        $mockNextLevel = Mockery::mock(MstVipLevel::class);
        $mockNextLevel->shouldReceive('getRequiredPoint')->andReturn(1000);

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with(4)
            ->once()
            ->andReturn($mockNextLevel);

        // Act
        $result = $this->service->getPointsToNextLevel($currentLevel, $currentPoint);

        // Assert
        $this->assertSame(0, $result); // max(0, 1000 - 1500)
    }

    /**
     * @test
     * VIPレベルの特典情報を取得できる
     */
    public function VIPレベルの特典情報を取得できる(): void
    {
        // Arrange
        $level = 5;

        $mockVipLevel = Mockery::mock(MstVipLevel::class);
        $mockVipLevel->shouldReceive('getMaxStaminaBonus')->andReturn(50);
        $mockVipLevel->shouldReceive('getDailyDiamondBonus')->andReturn(10);
        $mockVipLevel->shouldReceive('getShopDiscountRate')->andReturn(0.1);
        $mockVipLevel->shouldReceive('getGachaDiscountRate')->andReturn(0.05);

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with($level)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->getBenefits($level);

        // Assert
        $this->assertInstanceOf(VipBenefitDto::class, $result);
        $this->assertSame(50, $result->getMaxStaminaBonus());
        $this->assertSame(10, $result->getDailyDiamondBonus());
        $this->assertSame(0.1, $result->getShopDiscountRate());
        $this->assertSame(0.05, $result->getGachaDiscountRate());
    }

    /**
     * @test
     * 存在しないVIPレベルの特典取得は例外が発生する
     */
    public function 存在しないVIPレベルの特典取得は例外が発生する(): void
    {
        // Arrange
        $level = 999;

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with($level)
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(VipLevelNotFoundException::class);
        $this->expectExceptionMessage('VIP level 999 not found');

        // Act
        $this->service->getBenefits($level);
    }

    /**
     * @test
     * VIPレベルマスターデータを取得できる
     */
    public function VIPレベルマスターデータを取得できる(): void
    {
        // Arrange
        $level = 3;
        $mockVipLevel = Mockery::mock(MstVipLevel::class);

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with($level)
            ->once()
            ->andReturn($mockVipLevel);

        // Act
        $result = $this->service->getVipLevelMaster($level);

        // Assert
        $this->assertSame($mockVipLevel, $result);
    }

    /**
     * @test
     * 存在しないVIPレベルマスターデータ取得は例外が発生する
     */
    public function 存在しないVIPレベルマスターデータ取得は例外が発生する(): void
    {
        // Arrange
        $level = 999;

        $this->vipLevelRepository
            ->shouldReceive('findByLevel')
            ->with($level)
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(VipLevelNotFoundException::class);
        $this->expectExceptionMessage('VIP level 999 not found');

        // Act
        $this->service->getVipLevelMaster($level);
    }

    /**
     * @test
     * 全VIPレベルのリストを取得できる
     */
    public function 全VIPレベルのリストを取得できる(): void
    {
        // Arrange
        $mockLevel1 = Mockery::mock(MstVipLevel::class);
        $mockLevel1->shouldReceive('getLevel')->andReturn(1);
        $mockLevel1->shouldReceive('getRequiredPoint')->andReturn(100);
        $mockLevel1->shouldReceive('getMaxStaminaBonus')->andReturn(10);
        $mockLevel1->shouldReceive('getDailyDiamondBonus')->andReturn(5);
        $mockLevel1->shouldReceive('getShopDiscountRate')->andReturn(0.05);
        $mockLevel1->shouldReceive('getGachaDiscountRate')->andReturn(0.02);
        $mockLevel1->shouldReceive('getDisplayBadgeUrl')->andReturn('https://example.com/badge1.png');

        $mockLevel2 = Mockery::mock(MstVipLevel::class);
        $mockLevel2->shouldReceive('getLevel')->andReturn(2);
        $mockLevel2->shouldReceive('getRequiredPoint')->andReturn(500);
        $mockLevel2->shouldReceive('getMaxStaminaBonus')->andReturn(20);
        $mockLevel2->shouldReceive('getDailyDiamondBonus')->andReturn(10);
        $mockLevel2->shouldReceive('getShopDiscountRate')->andReturn(0.1);
        $mockLevel2->shouldReceive('getGachaDiscountRate')->andReturn(0.05);
        $mockLevel2->shouldReceive('getDisplayBadgeUrl')->andReturn('https://example.com/badge2.png');

        $collection = new CustomCollection([$mockLevel1, $mockLevel2]);

        $this->vipLevelRepository
            ->shouldReceive('getAllLevels')
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->getAllLevels();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['level']);
        $this->assertSame(100, $result[0]['required_point']);
        $this->assertSame(2, $result[1]['level']);
        $this->assertSame(500, $result[1]['required_point']);
    }
}
