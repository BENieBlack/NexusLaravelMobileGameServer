<?php

namespace NexusLogin\Tests\Unit\Services;

use NexusLogin\Services\LoginBonusService;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusResource\DTOs\ResourceDto;
use Carbon\CarbonImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class LoginBonusServiceTest extends TestCase
{
    private LoginBonusService $service;
    private LoginBonusRepositoryInterface $bonusRepository;
    private LoginBonusHistoryRepositoryInterface $historyRepository;
    private ResourceDeliveryService $deliveryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bonusRepository = Mockery::mock(LoginBonusRepositoryInterface::class);
        $this->historyRepository = Mockery::mock(LoginBonusHistoryRepositoryInterface::class);
        $this->deliveryService = Mockery::mock(ResourceDeliveryService::class);

        $this->service = new LoginBonusService(
            $this->deliveryService,
            $this->bonusRepository,
            $this->historyRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 初回ログイン時にログインボーナスを配布
     */
    public function test_check_and_grant_login_bonus_first_time(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-15 10:00:00');
        
        // 履歴なし（初回ログイン）
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn(null);

        // ループ日数を取得
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(7);

        // 1日目のボーナスを取得
        $bonusData = [
            'id' => 'login_bonus_001',
            'day' => 1,
            'is_active' => true,
        ];
        $this->bonusRepository->shouldReceive('findActiveByDay')
            ->once()
            ->with(1)
            ->andReturn($bonusData);

        // ボーナス内容を取得
        $contents = [
            [
                'id' => 'content_001',
                'mst_login_bonus_id' => 'login_bonus_001',
                'content_type' => 'currency',
                'content_id' => 'gold',
                'amount' => 100,
                'is_paid' => false,
            ],
        ];
        $this->bonusRepository->shouldReceive('findContentsByLoginBonusId')
            ->once()
            ->with('login_bonus_001')
            ->andReturn($contents);

        // リソース配布
        $this->deliveryService->shouldReceive('addResources')
            ->once()
            ->with(Mockery::on(function ($resources) {
                return is_array($resources)
                    && count($resources) === 1
                    && $resources[0] instanceof ResourceDto
                    && $resources[0]->getType() === 'currency'
                    && $resources[0]->getId() === 'gold'
                    && $resources[0]->getAmount() === 100;
            }));

        $this->deliveryService->shouldReceive('deliver')
            ->once()
            ->with($sysPlayerId);

        // 履歴記録
        $this->historyRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($sysPlayerId) {
                return $data['sys_player_id'] === $sysPlayerId
                    && $data['mst_login_bonus_id'] === 'login_bonus_001'
                    && $data['reward_type'] === 'currency'
                    && $data['reward_id'] === 'gold'
                    && $data['reward_amount'] === 100
                    && $data['is_paid'] === false;
            }), $connectionName);

        // Act
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, null, $connectionName, $now);

        // Assert
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ResourceDto::class, $result[0]);
        $this->assertEquals('currency', $result[0]->getType());
        $this->assertEquals('gold', $result[0]->getId());
        $this->assertEquals(100, $result[0]->getAmount());
    }

    /**
     * 同じゲーム内日付の再ログイン時はボーナスを配布しない
     */
    public function test_check_and_grant_login_bonus_same_game_day(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        
        // 現在時刻: 2026-01-15 10:00:00 UTC
        $now = CarbonImmutable::parse('2026-01-15 10:00:00');
        
        // 最終ログイン時刻: 2026-01-15 05:00:00 UTC（同じゲーム内日付）
        // ゲーム内日付は4:00 UTCを基準とするため、両方とも2026-01-15のゲーム内日付
        $lastLoginAt = '2026-01-15 05:00:00';

        // Act
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, $lastLoginAt, $connectionName, $now);

        // Assert
        $this->assertEmpty($result);
    }

    /**
     * 連続ログイン2日目にボーナスを配布
     */
    public function test_check_and_grant_login_bonus_consecutive_day_2(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-16 10:00:00');
        
        // 昨日（ゲーム内日付）の履歴あり
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn([
                'sys_player_id' => $sysPlayerId,
                'received_date' => '2026-01-15 04:00:00', // 昨日のゲーム内日付開始時刻
            ]);

        // 過去7日間のユニーク日数をカウント
        $this->historyRepository->shouldReceive('countUniqueDaysSince')
            ->once()
            ->with($sysPlayerId, Mockery::any(), $connectionName)
            ->andReturn(1); // 昨日の1日分

        // ループ日数を取得
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(7);

        // 2日目のボーナスを取得
        $bonusData = [
            'id' => 'login_bonus_002',
            'day' => 2,
            'is_active' => true,
        ];
        $this->bonusRepository->shouldReceive('findActiveByDay')
            ->once()
            ->with(2)
            ->andReturn($bonusData);

        // ボーナス内容を取得
        $contents = [
            [
                'id' => 'content_002',
                'mst_login_bonus_id' => 'login_bonus_002',
                'content_type' => 'currency',
                'content_id' => 'gold',
                'amount' => 200,
                'is_paid' => false,
            ],
        ];
        $this->bonusRepository->shouldReceive('findContentsByLoginBonusId')
            ->once()
            ->with('login_bonus_002')
            ->andReturn($contents);

        // リソース配布
        $this->deliveryService->shouldReceive('addResources')->once();
        $this->deliveryService->shouldReceive('deliver')->once()->with($sysPlayerId);
        $this->historyRepository->shouldReceive('create')->once();

        // Act
        $lastLoginAt = '2026-01-15 10:00:00'; // 昨日のログイン
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, $lastLoginAt, $connectionName, $now);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(200, $result[0]->getAmount());
    }

    /**
     * 連続ログインが途切れた場合は1日目から再開
     */
    public function test_check_and_grant_login_bonus_reset_after_break(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-17 10:00:00');
        
        // 2日前（ゲーム内日付）の履歴あり（昨日はログインなし）
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn([
                'sys_player_id' => $sysPlayerId,
                'received_date' => '2026-01-15 04:00:00', // 2日前のゲーム内日付開始時刻
            ]);

        // ループ日数を取得
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(7);

        // 1日目のボーナスを取得（連続ログインリセット）
        $bonusData = [
            'id' => 'login_bonus_001',
            'day' => 1,
            'is_active' => true,
        ];
        $this->bonusRepository->shouldReceive('findActiveByDay')
            ->once()
            ->with(1)
            ->andReturn($bonusData);

        // ボーナス内容を取得
        $contents = [
            [
                'id' => 'content_001',
                'mst_login_bonus_id' => 'login_bonus_001',
                'content_type' => 'currency',
                'content_id' => 'gold',
                'amount' => 100,
                'is_paid' => false,
            ],
        ];
        $this->bonusRepository->shouldReceive('findContentsByLoginBonusId')
            ->once()
            ->with('login_bonus_001')
            ->andReturn($contents);

        // リソース配布
        $this->deliveryService->shouldReceive('addResources')->once();
        $this->deliveryService->shouldReceive('deliver')->once()->with($sysPlayerId);
        $this->historyRepository->shouldReceive('create')->once();

        // Act
        $lastLoginAt = '2026-01-15 10:00:00'; // 2日前のログイン
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, $lastLoginAt, $connectionName, $now);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[0]->getAmount()); // 1日目のボーナス
    }

    /**
     * ボーナスマスターが存在しない場合は何も配布しない
     */
    public function test_check_and_grant_login_bonus_no_bonus_master(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-15 10:00:00');
        
        // 初回ログイン
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn(null);

        // ループ日数がnull（ボーナスマスターなし）
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, null, $connectionName, $now);

        // Assert
        $this->assertEmpty($result);
    }

    /**
     * ボーナス内容が空の場合は何も配布しない
     */
    public function test_check_and_grant_login_bonus_no_contents(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-15 10:00:00');
        
        // 初回ログイン
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn(null);

        // ループ日数を取得
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(7);

        // ボーナスデータは存在
        $bonusData = [
            'id' => 'login_bonus_001',
            'day' => 1,
            'is_active' => true,
        ];
        $this->bonusRepository->shouldReceive('findActiveByDay')
            ->once()
            ->with(1)
            ->andReturn($bonusData);

        // ボーナス内容が空
        $this->bonusRepository->shouldReceive('findContentsByLoginBonusId')
            ->once()
            ->with('login_bonus_001')
            ->andReturn([]);

        // Act
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, null, $connectionName, $now);

        // Assert
        $this->assertEmpty($result);
    }

    /**
     * 8日目は1日目にループ（7日周期）
     */
    public function test_check_and_grant_login_bonus_loop_on_day_8(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $connectionName = 'trx1';
        $now = CarbonImmutable::parse('2026-01-22 10:00:00');
        
        // 7日連続ログイン
        $this->historyRepository->shouldReceive('findLatestByPlayer')
            ->once()
            ->with($sysPlayerId, $connectionName)
            ->andReturn([
                'sys_player_id' => $sysPlayerId,
                'received_date' => '2026-01-21 04:00:00', // 昨日
            ]);

        // 過去7日間で7日分
        $this->historyRepository->shouldReceive('countUniqueDaysSince')
            ->once()
            ->with($sysPlayerId, Mockery::any(), $connectionName)
            ->andReturn(7);

        // ループ日数を取得
        $this->bonusRepository->shouldReceive('getLoopDaysForActiveBonus')
            ->once()
            ->andReturn(7);

        // 8日目 = (8-1) % 7 + 1 = 1 なので1日目のボーナスを取得
        $bonusData = [
            'id' => 'login_bonus_001',
            'day' => 1,
            'is_active' => true,
        ];
        $this->bonusRepository->shouldReceive('findActiveByDay')
            ->once()
            ->with(1)
            ->andReturn($bonusData);

        // ボーナス内容を取得
        $contents = [
            [
                'id' => 'content_001',
                'mst_login_bonus_id' => 'login_bonus_001',
                'content_type' => 'currency',
                'content_id' => 'gold',
                'amount' => 100,
                'is_paid' => false,
            ],
        ];
        $this->bonusRepository->shouldReceive('findContentsByLoginBonusId')
            ->once()
            ->with('login_bonus_001')
            ->andReturn($contents);

        // リソース配布
        $this->deliveryService->shouldReceive('addResources')->once();
        $this->deliveryService->shouldReceive('deliver')->once()->with($sysPlayerId);
        $this->historyRepository->shouldReceive('create')->once();

        // Act
        $lastLoginAt = '2026-01-21 10:00:00'; // 昨日のログイン
        $result = $this->service->checkAndGrantLoginBonus($sysPlayerId, $lastLoginAt, $connectionName, $now);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[0]->getAmount()); // 1日目のボーナスにループ
    }
}
