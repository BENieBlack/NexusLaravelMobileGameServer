<?php

namespace NexusStamina\Tests\Unit\Services;

use Mockery;
use Nexus\Core\Utilities\ClockUtility;
use NexusStamina\Constants\StaminaConst;
use NexusStamina\DataTransferObjects\Stamina;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Services\PlayerLevelServiceInterface;
use NexusStamina\Services\StaminaService;
use PHPUnit\Framework\TestCase;

class StaminaServiceTest extends TestCase
{
    private StaminaService $service;

    private StaminaRepositoryInterface $staminaRepository;

    private PlayerLevelServiceInterface $playerLevelService;

    /**
     * テストで使うスタミナDTOの最終回復時刻
     *
     * 既定ではここに現在時刻を合わせ、経過時間による自然回復が
     * 混ざらない状態で各機能を検証する。
     */
    private const LAST_RECOVERY_AT = '2026-01-15 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow(self::LAST_RECOVERY_AT);

        $this->staminaRepository = Mockery::mock(StaminaRepositoryInterface::class);
        $this->playerLevelService = Mockery::mock(PlayerLevelServiceInterface::class);

        $this->service = new StaminaService(
            $this->staminaRepository,
            $this->playerLevelService
        );
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();
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

        $createdStamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: $initialStamina,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function ($dto) use ($sysPlayerId, $initialStamina) {
                return $dto instanceof Stamina
                    && $dto->getSysPlayerId() === $sysPlayerId
                    && $dto->getCurrentStamina() === $initialStamina
                    && $dto->getType() === StaminaConst::TYPE_NORMAL
                    && $dto->getRecoveryRateMultiplier() === 1.00;
            }))
            ->andReturn($createdStamina);

        // Act
        $result = $this->service->initializeStamina($sysPlayerId, $initialStamina);

        // Assert
        $this->assertInstanceOf(Stamina::class, $result);
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
        $result = $this->service->findStamina($sysPlayerId);

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

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 100,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
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

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 100,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
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

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
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

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
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

    /**
     * 時間経過で自然回復する
     */
    public function test_find_stamina_applies_auto_recovery(): void
    {
        // Arrange
        $sysPlayerId = 1;

        // 最終回復から25分経過（300秒で1ポイント回復するので5ポイント）
        ClockUtility::setNow('2026-01-15 10:25:00');

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        // Act
        $result = $this->service->findStamina($sysPlayerId);

        // Assert
        $this->assertSame(55, $result->getCurrentStamina());
        // 回復に使った分だけ基準時刻が進む
        $this->assertSame('2026-01-15 10:25:00', $result->getLastRecoveryAt());
    }

    /**
     * 自然回復は最大値で頭打ちになる
     */
    public function test_find_stamina_auto_recovery_stops_at_max(): void
    {
        // Arrange
        $sysPlayerId = 1;

        // 10時間経過（120ポイント分だが最大値100で止まる）
        ClockUtility::setNow('2026-01-15 20:00:00');

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 1.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        // Act
        $result = $this->service->findStamina($sysPlayerId);

        // Assert
        $this->assertSame(100, $result->getCurrentStamina());
    }

    /**
     * 回復速度倍率が効く
     */
    public function test_find_stamina_auto_recovery_applies_multiplier(): void
    {
        // Arrange
        $sysPlayerId = 1;

        // 25分経過。倍率2.0なら50分相当で10ポイント回復する
        ClockUtility::setNow('2026-01-15 10:25:00');

        $stamina = new Stamina(
            sysPlayerId: $sysPlayerId,
            type: StaminaConst::TYPE_NORMAL,
            currentStamina: 50,
            recoveryRateMultiplier: 2.00,
            lastRecoveryAt: self::LAST_RECOVERY_AT
        );

        $this->staminaRepository->shouldReceive('selectByPlayerAndType')
            ->once()
            ->with($sysPlayerId, StaminaConst::TYPE_NORMAL)
            ->andReturn($stamina);

        $this->playerLevelService->shouldReceive('findMaxStamina')
            ->once()
            ->with($sysPlayerId)
            ->andReturn(100);

        // Act
        $result = $this->service->findStamina($sysPlayerId);

        // Assert
        $this->assertSame(60, $result->getCurrentStamina());
    }
}
