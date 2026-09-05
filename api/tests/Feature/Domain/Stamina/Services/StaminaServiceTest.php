<?php

namespace Tests\Feature\Domain\Stamina\Services;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Domain\Stamina\Services\StaminaService;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * StaminaService（Domain層）のテスト
 *
 * 消費・回復はパッケージ層に委譲するため、
 * ここではEloquentモデルを跨いだ振る舞いを確認する。
 */
class StaminaServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    /** レベル1の最大スタミナ */
    private const MAX_STAMINA = 50;

    /** 1ポイントあたりの回復間隔（秒） */
    private const RECOVERY_INTERVAL_SECONDS = 300;

    private int $sysPlayerId = 1;

    private StaminaService $service;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的に制御するため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        $this->service = app(StaminaService::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
        $this->createLevelMaster();
        $this->createPlayer();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function スタミナを初期化できる(): void
    {
        $this->service->initializeStamina($this->sysPlayerId, 30);
        $this->queryManager->execAllQuery();

        $stamina = $this->findStaminaRow();
        $this->assertNotNull($stamina);
        $this->assertSame(30, $stamina->current_stamina);
        $this->assertSame(StaminaConst::TYPE_NORMAL, $stamina->type);
        $this->assertSame('1.00', (string) $stamina->recovery_rate_multiplier);
    }

    #[Test]
    public function スタミナが無い場合はnullを返す(): void
    {
        $this->assertNull($this->service->findStamina($this->sysPlayerId));
    }

    #[Test]
    public function 回復速度倍率を更新できる(): void
    {
        $this->createStamina(currentStamina: 10);

        $this->service->updateRecoveryRateMultiplier(2.0);
        $this->queryManager->execAllQuery();

        $this->assertSame('2.00', (string) $this->findStaminaRow()->recovery_rate_multiplier);
    }

    #[Test]
    public function スタミナが無ければ倍率の更新は何もしない(): void
    {
        $this->service->updateRecoveryRateMultiplier(2.0);
        $this->queryManager->execAllQuery();

        $this->assertNull($this->findStaminaRow());
    }

    #[Test]
    public function 取得時に時間経過分が自然回復する(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');
        // 25分前が最終回復。300秒で1ポイント回復するので5ポイント
        $this->createStamina(currentStamina: 10, lastRecoveryAt: '2026-03-15 11:35:00');

        $trxStamina = $this->service->findStamina($this->sysPlayerId);

        $this->assertSame(15, $trxStamina->getCurrentStamina());
        $this->assertSame('2026-03-15 12:00:00', (string) $trxStamina->last_recovery_at);

        // 読み取りではDBに書き込まない（消費・回復時に同じ計算を通って永続化される）
        $this->queryManager->execAllQuery();
        $row = $this->findStaminaRow();
        $this->assertSame(10, $row->current_stamina);
    }

    #[Test]
    public function 自然回復は最大値で頭打ちになる(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');
        // 10時間前（120ポイント分）だが最大値50で止まる
        $this->createStamina(currentStamina: 10, lastRecoveryAt: '2026-03-15 02:00:00');

        $this->assertSame(self::MAX_STAMINA, $this->service->findStamina($this->sysPlayerId)->getCurrentStamina());
    }

    #[Test]
    public function 満タンなら完全回復までの時間は0秒(): void
    {
        $this->createStamina(currentStamina: self::MAX_STAMINA);

        $this->assertSame(0, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    #[Test]
    public function 不足分から完全回復までの時間を計算する(): void
    {
        $this->createStamina(currentStamina: self::MAX_STAMINA - 3);

        // 3ポイント × 300秒
        $this->assertSame(900, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    #[Test]
    public function 回復速度倍率が高いほど完全回復までの時間は短い(): void
    {
        $this->createStamina(currentStamina: self::MAX_STAMINA - 3, recoveryRateMultiplier: 2.0);

        // 900秒 ÷ 2.0
        $this->assertSame(450, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    #[Test]
    public function スタミナが無ければ完全回復までの時間は0秒(): void
    {
        $this->assertSame(0, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    private function findStaminaRow(): ?object
    {
        return DB::connection('trx1')->table('trx_stamina')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('type', StaminaConst::TYPE_NORMAL)
            ->first();
    }

    private function createLevelMaster(): void
    {
        DB::connection('mst')->table('mst_player_level')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'level' => 1,
            'required_exp' => 0,
            'max_stamina' => self::MAX_STAMINA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPlayer(): void
    {
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-stamina',
            'my_id' => 'TEST0004',
            'name' => 'tester',
            'level' => 1,
            'level_exp' => 0,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStamina(
        int $currentStamina,
        float $recoveryRateMultiplier = 1.0,
        ?string $lastRecoveryAt = null
    ): void {
        DB::connection('trx1')->table('trx_stamina')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'type' => StaminaConst::TYPE_NORMAL,
            'current_stamina' => $currentStamina,
            'recovery_rate_multiplier' => $recoveryRateMultiplier,
            'last_recovery_at' => $lastRecoveryAt ?? ClockUtility::nowToString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('trx1')->table('trx_stamina')->delete();
    }
}
