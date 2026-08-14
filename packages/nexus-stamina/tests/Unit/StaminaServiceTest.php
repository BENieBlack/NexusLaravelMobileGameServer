<?php

namespace NexusStamina\Tests\Unit;

use NexusStamina\Dto\StaminaDto;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Services\PlayerLevelServiceInterface;
use NexusStamina\Services\StaminaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * StaminaService のユニットテスト
 */
class StaminaServiceTest extends TestCase
{
    private StaminaRepositoryInterface&MockObject $staminaRepository;

    private PlayerLevelServiceInterface&MockObject $levelService;

    private StaminaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staminaRepository = $this->createMock(StaminaRepositoryInterface::class);
        $this->levelService = $this->createMock(PlayerLevelServiceInterface::class);

        $this->service = new StaminaService(
            $this->staminaRepository,
            $this->levelService
        );
    }

    /**
     * スタミナ取得のテスト
     */
    public function test_get_stamina_returns_stamina_with_auto_recovery(): void
    {
        $playerId = 1;
        $type = 'normal';
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        // 経過時間は現在時刻基準なので、テストは現在のスタミナのみチェック
        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s') // 現在時刻
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $result = $this->service->findStamina($playerId, $type);

        $this->assertInstanceOf(StaminaDto::class, $result);
        // 現在時刻なので回復は発生しない
        $this->assertEquals(50, $result->getCurrentStamina());
    }

    /**
     * スタミナが存在しない場合のテスト
     */
    public function test_get_stamina_returns_null_when_not_found(): void
    {
        $playerId = 1;
        $type = 'normal';

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn(null);

        $result = $this->service->findStamina($playerId, $type);

        $this->assertNull($result);
    }

    /**
     * スタミナ消費のテスト
     */
    public function test_consume_stamina_success(): void
    {
        $playerId = 1;
        $type = 'normal';
        $amount = 10;
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $this->staminaRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($dto) {
                return $dto->getCurrentStamina() === 40;
            }));

        $result = $this->service->consumeStamina($playerId, $amount, $type);

        $this->assertTrue($result['success']);
        $this->assertEquals(40, $result['remaining']);
    }

    /**
     * スタミナ不足時のテスト
     */
    public function test_consume_stamina_insufficient(): void
    {
        $playerId = 1;
        $type = 'normal';
        $amount = 60;
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $this->staminaRepository
            ->expects($this->never())
            ->method('persist');

        $result = $this->service->consumeStamina($playerId, $amount, $type);

        $this->assertFalse($result['success']);
        $this->assertEquals(50, $result['remaining']);
    }

    /**
     * アイテムによるスタミナ回復のテスト
     */
    public function test_recover_stamina_by_item_success(): void
    {
        $playerId = 1;
        $type = 'normal';
        $amount = 20;
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $this->staminaRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($dto) {
                return $dto->getCurrentStamina() === 70;
            }));

        $result = $this->service->recoverStaminaByItem($playerId, $amount, $type);

        $this->assertTrue($result['success']);
        $this->assertEquals(70, $result['total']);
    }

    /**
     * 最大値を超えるアイテム回復のテスト
     */
    public function test_recover_stamina_by_item_exceeds_max(): void
    {
        $playerId = 1;
        $type = 'normal';
        $amount = 60;
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 90,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $this->staminaRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($dto) {
                // 最大値を超えることができる
                return $dto->getCurrentStamina() === 150;
            }));

        $result = $this->service->recoverStaminaByItem($playerId, $amount, $type);

        $this->assertTrue($result['success']);
        $this->assertEquals(150, $result['total']);
    }

    /**
     * 次回回復までの時間計算テスト
     */
    public function test_get_time_until_next_recovery(): void
    {
        $playerId = 1;
        $type = 'normal';
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        // 現在時刻のスタミナ
        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 50,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $result = $this->service->calcTimeUntilNextRecovery($playerId, $type);

        // 経過0秒なので、次回まで300秒
        $this->assertEquals(300, $result);
    }

    /**
     * 最大値時は次回回復時間がnullになることをテスト
     */
    public function test_get_time_until_next_recovery_returns_null_when_full(): void
    {
        $playerId = 1;
        $type = 'normal';
        $maxStamina = 100;

        $this->levelService
            ->expects($this->once())
            ->method("findMaxStamina")
            ->with($playerId)
            ->willReturn($maxStamina);

        $staminaDto = new StaminaDto(
            sysPlayerId: $playerId,
            type: $type,
            currentStamina: 100,
            recoveryRateMultiplier: 1.0,
            lastRecoveryAt: date('Y-m-d H:i:s')
        );

        $this->staminaRepository
            ->expects($this->once())
            ->method('selectByPlayerAndType')
            ->with($playerId, $type)
            ->willReturn($staminaDto);

        $result = $this->service->calcTimeUntilNextRecovery($playerId, $type);

        $this->assertNull($result);
    }
}
