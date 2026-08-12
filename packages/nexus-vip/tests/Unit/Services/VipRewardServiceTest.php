<?php

namespace NexusVip\Tests\Unit\Services;

use Mockery;
use Nexus\Core\Support\CustomCollection;
use NexusVip\ValueObjects\VipReward;
use NexusVip\Models\MstVipLevelReward;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;
use NexusVip\Services\VipRewardService;
use PHPUnit\Framework\TestCase;

/**
 * VipRewardServiceのユニットテスト
 */
class VipRewardServiceTest extends TestCase
{
    private VipLevelRewardRepositoryInterface $vipLevelRewardRepository;

    private VipRewardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vipLevelRewardRepository = Mockery::mock(VipLevelRewardRepositoryInterface::class);
        $this->service = new VipRewardService($this->vipLevelRewardRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * VIPレベルに対応する報酬一覧を取得できる
     */
    public function vi_pレベルに対応する報酬一覧を取得できる(): void
    {
        // Arrange
        $vipLevel = 5;

        $mockReward1 = Mockery::mock(MstVipLevelReward::class);
        $mockReward1->shouldReceive('getContentType')->andReturn('diamond');
        $mockReward1->shouldReceive('getContentId')->andReturn('free_diamond');
        $mockReward1->shouldReceive('getContentOption')->andReturn(null);
        $mockReward1->shouldReceive('getContentQuantity')->andReturn(100);
        $mockReward1->shouldReceive('getAmount')->andReturn(1);
        $mockReward1->shouldReceive('getIsPaid')->andReturn(false);

        $mockReward2 = Mockery::mock(MstVipLevelReward::class);
        $mockReward2->shouldReceive('getContentType')->andReturn('item');
        $mockReward2->shouldReceive('getContentId')->andReturn('item_001');
        $mockReward2->shouldReceive('getContentOption')->andReturn(['rarity' => 5]);
        $mockReward2->shouldReceive('getContentQuantity')->andReturn(10);
        $mockReward2->shouldReceive('getAmount')->andReturn(2);
        $mockReward2->shouldReceive('getIsPaid')->andReturn(false);

        $collection = new CustomCollection([$mockReward1, $mockReward2]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->getRewardsByLevel($vipLevel);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(VipReward::class, $result[0]);
        $this->assertSame('diamond', $result[0]->getContentType());
        $this->assertSame(100, $result[0]->getContentQuantity());
        $this->assertInstanceOf(VipReward::class, $result[1]);
        $this->assertSame('item', $result[1]->getContentType());
        $this->assertSame(10, $result[1]->getContentQuantity());
    }

    /**
     * @test
     * 報酬がない場合は空配列を返す
     */
    public function 報酬がない場合は空配列を返す(): void
    {
        // Arrange
        $vipLevel = 1;
        $collection = new CustomCollection([]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->getRewardsByLevel($vipLevel);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * @test
     * 報酬があるかチェックできる（報酬あり）
     */
    public function 報酬があるかチェックできる_報酬あり(): void
    {
        // Arrange
        $vipLevel = 5;

        $mockReward = Mockery::mock(MstVipLevelReward::class);
        $collection = new CustomCollection([$mockReward]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->hasRewards($vipLevel);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     * 報酬があるかチェックできる（報酬なし）
     */
    public function 報酬があるかチェックできる_報酬なし(): void
    {
        // Arrange
        $vipLevel = 1;
        $collection = new CustomCollection([]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->hasRewards($vipLevel);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     * 報酬を配列形式で取得できる
     */
    public function 報酬を配列形式で取得できる(): void
    {
        // Arrange
        $vipLevel = 3;

        $mockReward = Mockery::mock(MstVipLevelReward::class);
        $mockReward->shouldReceive('getContentType')->andReturn('diamond');
        $mockReward->shouldReceive('getContentId')->andReturn('paid_diamond');
        $mockReward->shouldReceive('getContentOption')->andReturn(null);
        $mockReward->shouldReceive('getContentQuantity')->andReturn(500);
        $mockReward->shouldReceive('getAmount')->andReturn(1);
        $mockReward->shouldReceive('getIsPaid')->andReturn(true);

        $collection = new CustomCollection([$mockReward]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->getRewardsArray($vipLevel);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('content_type', $result[0]);
        $this->assertArrayHasKey('content_id', $result[0]);
        $this->assertArrayHasKey('content_quantity', $result[0]);
        $this->assertArrayHasKey('amount', $result[0]);
        $this->assertArrayHasKey('is_paid', $result[0]);
        $this->assertSame('diamond', $result[0]['content_type']);
        $this->assertSame('paid_diamond', $result[0]['content_id']);
        $this->assertSame(500, $result[0]['content_quantity']);
        $this->assertTrue($result[0]['is_paid']);
    }

    /**
     * @test
     * 報酬の総量が正しく計算される
     */
    public function 報酬の総量が正しく計算される(): void
    {
        // Arrange
        $vipLevel = 5;

        $mockReward = Mockery::mock(MstVipLevelReward::class);
        $mockReward->shouldReceive('getContentType')->andReturn('item');
        $mockReward->shouldReceive('getContentId')->andReturn('item_999');
        $mockReward->shouldReceive('getContentOption')->andReturn(null);
        $mockReward->shouldReceive('getContentQuantity')->andReturn(10);
        $mockReward->shouldReceive('getAmount')->andReturn(5);
        $mockReward->shouldReceive('getIsPaid')->andReturn(false);

        $collection = new CustomCollection([$mockReward]);

        $this->vipLevelRewardRepository
            ->shouldReceive('findActiveByVipLevel')
            ->with($vipLevel)
            ->once()
            ->andReturn($collection);

        // Act
        $result = $this->service->getRewardsArray($vipLevel);

        // Assert
        $this->assertSame(50, $result[0]['total_quantity']); // 10 * 5
    }
}
