<?php

namespace NexusVip\Tests\Unit\Services;

use Mockery;
use NexusVip\DTOs\PlayerVipDto;
use NexusVip\Exceptions\InvalidVipPointException;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use NexusVip\Services\VipLevelService;
use NexusVip\Services\VipPointService;
use NexusVip\Services\VipRewardService;
use NexusVip\ValueObjects\VipConfig;
use PHPUnit\Framework\TestCase;

/**
 * VipPointServiceのユニットテスト
 */
class VipPointServiceTest extends TestCase
{
    private PlayerVipRepositoryInterface $playerVipRepository;

    private VipPointLogRepositoryInterface $vipPointLogRepository;

    private VipLevelService $vipLevelService;

    private VipRewardService $vipRewardService;

    private VipConfig $config;

    private VipPointService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->playerVipRepository = Mockery::mock(PlayerVipRepositoryInterface::class);
        $this->vipPointLogRepository = Mockery::mock(VipPointLogRepositoryInterface::class);
        $this->vipLevelService = Mockery::mock(VipLevelService::class);
        $this->vipRewardService = Mockery::mock(VipRewardService::class);

        // テスト用設定（ログ・イベント無効）
        $this->config = new VipConfig(
            enablePointLog: false,
            enableLevelUpEvent: false,
            staminaBonusEnabled: true,
            shopDiscountEnabled: true,
            gachaDiscountEnabled: true,
            dailyDiamondEnabled: true,
        );

        $this->service = new VipPointService(
            $this->playerVipRepository,
            $this->vipPointLogRepository,
            $this->vipLevelService,
            $this->vipRewardService,
            $this->config
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * VIPポイントを正常に付与できる
     */
    public function vi_pポイントを付与できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $points = 100;
        $reason = 'purchase';

        $playerVipDto = new PlayerVipDto(
            sysPlayerId: $sysPlayerId,
            vipPoint: 0,
            totalPaidAmount: 0.0
        );

        $this->playerVipRepository
            ->shouldReceive('findVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVipDto);

        $this->vipLevelService
            ->shouldReceive('calculateLevel')
            ->with(0)
            ->once()
            ->andReturn(0);

        $this->vipLevelService
            ->shouldReceive('calculateLevel')
            ->with(100)
            ->once()
            ->andReturn(1);

        $this->playerVipRepository
            ->shouldReceive('persistVipInfo')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto instanceof PlayerVipDto
                    && $dto->getVipPoint() === 100
                    && $dto->getSysPlayerId() === 1;
            }));

        // Act
        $result = $this->service->addPoints($sysPlayerId, $points, $reason);

        // Assert
        $this->assertSame(100, $result->getVipPoint());
        $this->assertSame($sysPlayerId, $result->getSysPlayerId());
    }

    /**
     * @test
     * 課金額も同時に累積できる
     */
    public function 課金額も同時に累積できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $points = 100;
        $reason = 'purchase';
        $metadata = ['purchase_amount_jpy' => 1000.0];

        $playerVipDto = new PlayerVipDto(
            sysPlayerId: $sysPlayerId,
            vipPoint: 0,
            totalPaidAmount: 0.0
        );

        $this->playerVipRepository
            ->shouldReceive('findVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVipDto);

        $this->vipLevelService
            ->shouldReceive('calculateLevel')
            ->andReturn(0, 1);

        $this->playerVipRepository
            ->shouldReceive('persistVipInfo')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto instanceof PlayerVipDto
                    && $dto->getVipPoint() === 100
                    && $dto->getTotalPaidAmount() === 1000.0;
            }));

        // Act
        $result = $this->service->addPoints($sysPlayerId, $points, $reason, $metadata);

        // Assert
        $this->assertSame(100, $result->getVipPoint());
        $this->assertSame(1000.0, $result->getTotalPaidAmount());
    }

    /**
     * @test
     * ゼロ以下のポイントは例外が発生する
     */
    public function ゼロ以下のポイントは例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $points = 0;
        $reason = 'purchase';

        // Expect
        $this->expectException(InvalidVipPointException::class);
        $this->expectExceptionMessage('Points must be positive, got: 0');

        // Act
        $this->service->addPoints($sysPlayerId, $points, $reason);
    }

    /**
     * @test
     * 存在しないプレイヤーの場合は例外が発生する
     */
    public function 存在しないプレイヤーの場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $points = 100;
        $reason = 'purchase';

        $this->playerVipRepository
            ->shouldReceive('findVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(InvalidVipPointException::class);
        $this->expectExceptionMessage('Player not found: 999');

        // Act
        $this->service->addPoints($sysPlayerId, $points, $reason);
    }

    /**
     * @test
     * プレイヤーのVIP情報を取得できる
     */
    public function プレイヤーの_vi_p情報を取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $playerVipDto = new PlayerVipDto(
            sysPlayerId: $sysPlayerId,
            vipPoint: 500,
            totalPaidAmount: 5000.0
        );

        $this->playerVipRepository
            ->shouldReceive('findVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVipDto);

        // Act
        $result = $this->service->getPlayerVipInfo($sysPlayerId);

        // Assert
        $this->assertSame(500, $result->getVipPoint());
        $this->assertSame(5000.0, $result->getTotalPaidAmount());
    }

    /**
     * @test
     * 存在しないプレイヤーのVIP情報はnullを返す
     */
    public function 存在しないプレイヤーの_vi_p情報はnullを返す(): void
    {
        // Arrange
        $sysPlayerId = 999;

        $this->playerVipRepository
            ->shouldReceive('findVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->getPlayerVipInfo($sysPlayerId);

        // Assert
        $this->assertNull($result);
    }
}
