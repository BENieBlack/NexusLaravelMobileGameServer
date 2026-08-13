<?php

namespace NexusStamina\Tests\Unit\Services;

use Mockery;
use NexusStamina\Constants\StaminaConst;
use NexusStamina\Dto\StaminaDto;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Services\PlayerLevelServiceInterface;
use NexusStamina\Services\StaminaService;
use PHPUnit\Framework\TestCase;

class StaminaServiceTest extends TestCase
{
    private StaminaService $service;

    private StaminaRepositoryInterface $staminaRepository;

    private PlayerLevelServiceInterface $playerLevelService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staminaRepository = Mockery::mock(StaminaRepositoryInterface::class);
        $this->playerLevelService = Mockery::mock(PlayerLevelServiceInterface::class);

        $this->service = new StaminaService(
            $this->staminaRepository,
            $this->playerLevelService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * スタミナ初期化が正常に動作
     */
    public function test_initialize_stamina_creates_new_record(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $initialStamina = 100;

        $createdDto = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: $initialStamina,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: '2026-01-15 10:00:00'
        );

        $this->staminaRepository->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($dto) use ($sysPlayerId, $initialStamina) {
                return $dto instanceof StaminaDto
                    && $dto->getSysPlayerId() === $sysPlayerId
                    && $dto->getCurrentStamina() === $initialStamina
                    && $dto->getType() === StaminaConst::TYPE_NORMAL
                    && $dto->getRecoveryRateMultiplier() === 1.00;
            }))
            ->andReturn($createdDto);

        // Act
        $result = $this->service->initializeStamina($sysPlayerId, $initialStamina);

        // Assert
        $this->assertInstanceOf(StaminaDto::class, $result);
        $this->assertEquals($sysPlayerId, $result->getSysPlayerId());
        $this->assertEquals($initialStamina, $result->getCurrentStamina());
    }

    /**
     * スタミナ取得時にスタミナレコードが存在しない場合はnullを返す
     */
    public function test_get_stamina_returns_null_when_not_found(): void
    {
        // Arrange
        $sysPlayerId = 1;

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn(null);

        // Act
        $result = $this->service->getStamina($sysPlayerId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * スタミナ消費が成功する
     */
    public function test_consume_stamina_success(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 30;

        $stamina = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 100,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: '2026-01-15 10:00:00'
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('getMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        $this->staminaRepository->shouldReceive('persist')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto->getCurrentStamina() === 70; // 100 - 30
            }));

        // Act
        $result = $this->service->consumeStamina($sysPlayerId, $amount);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(70, $result['remaining']);
        $this->assertEquals('Stamina consumed successfully', $result['message']);
    }

    /**
     * スタミナ不足時は消費失敗
     */
    public function test_consume_stamina_fails_when_insufficient(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 150; // 現在スタミナ100より多い

        $stamina = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 100,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: '2026-01-15 10:00:00'
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('getMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        // Act
        $result = $this->service->consumeStamina($sysPlayerId, $amount);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(100, $result['remaining']);
        $this->assertEquals('Insufficient stamina', $result['message']);
    }

    /**
     * アイテムによるスタミナ回復が成功
     */
    public function test_recover_stamina_by_item_success(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 50;

        $stamina = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: '2026-01-15 10:00:00'
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('getMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        $this->staminaRepository->shouldReceive('persist')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto->getCurrentStamina() === 100; // 50 + 50
            }));

        // Act
        $result = $this->service->recoverStaminaByItem($sysPlayerId, $amount);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['total']);
        $this->assertEquals('Stamina recovered successfully', $result['message']);
    }

    /**
     * アイテム回復時、最大値を超過できる
     */
    public function test_recover_stamina_by_item_exceeds_max(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 100; // 現在50 + 100 = 150（最大値100を超過）

        $stamina = new StaminaDto(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: '2026-01-15 10:00:00'
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('getMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        $this->staminaRepository->shouldReceive('persist')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto->getCurrentStamina() === 150; // 最大値超過を許可
            }));

        // Act
        $result = $this->service->recoverStaminaByItem($sysPlayerId, $amount);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(150, $result['total']); // 最大値100を超過
    }

    /**
     * スタミナレコードが存在しない場合、消費は失敗
     */
    public function test_consume_stamina_fails_when_record_not_found(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 30;

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn(null);

        // Act
        $result = $this->service->consumeStamina($sysPlayerId, $amount);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertEquals('Stamina record not found', $result['message']);
    }

    /**
     * スタミナレコードが存在しない場合、アイテム回復は失敗
     */
    public function test_recover_stamina_by_item_fails_when_record_not_found(): void
    {
        // Arrange
        $sysPlayerId = 1;
        $amount = 50;

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn(null);

        // Act
        $result = $this->service->recoverStaminaByItem($sysPlayerId, $amount);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals('Stamina record not found', $result['message']);
    }
}
