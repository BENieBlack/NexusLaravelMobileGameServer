<?php

namespace NexusVip\Tests\Unit\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use NexusVip\DataTransferObjects\PlayerVip;
use NexusVip\Events\VipLevelUpEvent;
use NexusVip\Exceptions\InvalidVipPointException;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusVip\Repositories\VipPointLogRepositoryInterface;
use NexusVip\Services\VipLevelService;
use NexusVip\Services\VipPointService;
use NexusVip\Services\VipRewardService;
use NexusVip\ValueObjects\VipConfig;
use PHPUnit\Framework\Attributes\Test;
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
     * VIPポイントを正常に付与できる
     */
    #[Test]
    public function vi_pポイントを付与できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $points = 100;
        $reason = 'purchase';

        $playerVip = new PlayerVip(
            sysPlayerId: $sysPlayerId,
            vipPoint: 0,
            totalPaidAmount: 0.0
        );

        $this->playerVipRepository
            ->shouldReceive('selectVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVip);

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
                return $dto instanceof PlayerVip
                    && $dto->getVipPoint() === 100
                    && $dto->getSysPlayerId() === 1;
            }));

        // Act
        $result = $this->service->addPoints($sysPlayerId, $points, $reason);

        // Assert
        $this->assertSame(100, $result->getVipPoint());
        $this->assertSame($sysPlayerId, $result->getSysPlayerId());
    }

    #[Test]
    public function レベルアップすると報酬付きのイベントが配信される(): void
    {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof VipLevelUpEvent
                && $event->sysPlayerId === 1
                && $event->beforeLevel === 0
                && $event->afterLevel === 2
                // 複数レベル上がったときは各レベルの報酬をまとめて載せる
                && $event->rewards === [['content_mst_id' => 'lv1'], ['content_mst_id' => 'lv2']]));

        $service = $this->makeServiceWithEvents($events, enableLevelUpEvent: true);
        $this->givenLevelChange(beforeLevel: 0, afterLevel: 2);
        $this->vipRewardService->shouldReceive('buildRewardsArray')->with(1)->andReturn([['content_mst_id' => 'lv1']]);
        $this->vipRewardService->shouldReceive('buildRewardsArray')->with(2)->andReturn([['content_mst_id' => 'lv2']]);

        $result = $service->addPoints(1, 300, 'purchase');

        $this->assertSame(300, $result->getVipPoint());
    }

    #[Test]
    public function イベントが無効ならレベルアップしても配信されない(): void
    {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldNotReceive('dispatch');

        $service = $this->makeServiceWithEvents($events, enableLevelUpEvent: false);
        $this->givenLevelChange(beforeLevel: 0, afterLevel: 2);

        $result = $service->addPoints(1, 300, 'purchase');

        $this->assertSame(300, $result->getVipPoint());
    }

    #[Test]
    public function レベルが上がらなければ配信されない(): void
    {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldNotReceive('dispatch');

        $service = $this->makeServiceWithEvents($events, enableLevelUpEvent: true);
        $this->givenLevelChange(beforeLevel: 1, afterLevel: 1);

        $result = $service->addPoints(1, 300, 'purchase');

        $this->assertSame(300, $result->getVipPoint());
    }

    /**
     * 課金額も同時に累積できる
     */
    #[Test]
    public function 課金額も同時に累積できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $points = 100;
        $reason = 'purchase';
        $metadata = ['purchase_amount_jpy' => 1000.0];

        $playerVip = new PlayerVip(
            sysPlayerId: $sysPlayerId,
            vipPoint: 0,
            totalPaidAmount: 0.0
        );

        $this->playerVipRepository
            ->shouldReceive('selectVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVip);

        $this->vipLevelService
            ->shouldReceive('calculateLevel')
            ->andReturn(0, 1);

        $this->playerVipRepository
            ->shouldReceive('persistVipInfo')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto instanceof PlayerVip
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
     * ゼロ以下のポイントは例外が発生する
     */
    #[Test]
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
     * 存在しないプレイヤーの場合は例外が発生する
     */
    #[Test]
    public function 存在しないプレイヤーの場合は例外が発生する(): void
    {
        // Arrange
        $sysPlayerId = 999;
        $points = 100;
        $reason = 'purchase';

        $this->playerVipRepository
            ->shouldReceive('selectVipInfoById')
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
     * プレイヤーのVIP情報を取得できる
     */
    #[Test]
    public function プレイヤーの_vi_p情報を取得できる(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $playerVip = new PlayerVip(
            sysPlayerId: $sysPlayerId,
            vipPoint: 500,
            totalPaidAmount: 5000.0
        );

        $this->playerVipRepository
            ->shouldReceive('selectVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn($playerVip);

        // Act
        $result = $this->service->findPlayerVipInfo($sysPlayerId);

        // Assert
        $this->assertSame(500, $result->getVipPoint());
        $this->assertSame(5000.0, $result->getTotalPaidAmount());
    }

    /**
     * 存在しないプレイヤーのVIP情報はnullを返す
     */
    #[Test]
    public function 存在しないプレイヤーの_vi_p情報はnullを返す(): void
    {
        // Arrange
        $sysPlayerId = 999;

        $this->playerVipRepository
            ->shouldReceive('selectVipInfoById')
            ->with($sysPlayerId)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->findPlayerVipInfo($sysPlayerId);

        // Assert
        $this->assertNull($result);
    }

    private function makeServiceWithEvents(Dispatcher $events, bool $enableLevelUpEvent): VipPointService
    {
        return new VipPointService(
            $this->playerVipRepository,
            $this->vipPointLogRepository,
            $this->vipLevelService,
            $this->vipRewardService,
            new VipConfig(
                enablePointLog: false,
                enableLevelUpEvent: $enableLevelUpEvent,
                staminaBonusEnabled: true,
                shopDiscountEnabled: true,
                gachaDiscountEnabled: true,
                dailyDiamondEnabled: true,
            ),
            $events,
        );
    }

    private function givenLevelChange(int $beforeLevel, int $afterLevel): void
    {
        $this->playerVipRepository->shouldReceive('selectVipInfoById')
            ->andReturn(new PlayerVip(sysPlayerId: 1, vipPoint: 0, totalPaidAmount: 0.0));
        $this->playerVipRepository->shouldReceive('persistVipInfo');
        $this->vipLevelService->shouldReceive('calculateLevel')->with(0)->andReturn($beforeLevel);
        $this->vipLevelService->shouldReceive('calculateLevel')->with(300)->andReturn($afterLevel);
    }
}
