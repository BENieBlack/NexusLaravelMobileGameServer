<?php

namespace Tests\Feature\Domain\Stamina\Services;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Domain\Stamina\Services\StaminaService;
use App\Persistence\ApiSession;
use App\Repositories\Mst\MstConfigRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
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
        // 25分前が最終回復。mst_config に行が無いので既定値（300秒）で1ポイント回復し、5ポイント
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

        // 3ポイント × 既定の回復間隔（300秒）
        $this->assertSame(3 * MstConfigKey::StaminaRecoveryIntervalSeconds->defaultInt(), $this->service->calcTimeToFullRecovery($this->sysPlayerId));
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

    #[Test]
    public function mst_configの回復間隔に従って自然回復する(): void
    {
        $this->useRecoveryInterval(600);
        ClockUtility::setNow('2026-03-15 12:00:00');
        // 25分前が最終回復。600秒で1ポイントなので2ポイント（残り5分は持ち越し）
        $this->createStamina(currentStamina: 10, lastRecoveryAt: '2026-03-15 11:35:00');

        $trxStamina = $this->service->findStamina($this->sysPlayerId);

        $this->assertSame(12, $trxStamina->getCurrentStamina());
        $this->assertSame('2026-03-15 11:55:00', (string) $trxStamina->last_recovery_at);
    }

    #[Test]
    public function mst_configの回復間隔に従って完全回復までの時間が変わる(): void
    {
        $this->useRecoveryInterval(600);
        $this->createStamina(currentStamina: self::MAX_STAMINA - 3);

        // 3ポイント × 600秒
        $this->assertSame(1800, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    #[Test]
    public function mst_configの値が不正でも既定値で完全回復までの時間を計算する(): void
    {
        $this->useRecoveryInterval(0);
        $this->createStamina(currentStamina: self::MAX_STAMINA - 3);

        // 0 は不正なので既定値（300秒）。ゼロ除算・例外にならない
        $this->assertSame(900, $this->service->calcTimeToFullRecovery($this->sysPlayerId));
    }

    /**
     * mst_config の回復間隔の行を入れ、読み出し口を作り直す
     *
     * mst は Redis にテーブルごと持つので、キャッシュを消す。
     * 読み出し口は scoped（リクエスト内でメモ化）なので、サービスも解決し直す。
     */
    private function useRecoveryInterval(int $seconds): void
    {
        DB::connection('mst')->table('mst_config')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'id' => MstConfigKey::StaminaRecoveryIntervalSeconds->value,
            'value' => (string) $seconds,
            'value_type' => 'int',
            'description' => 'テスト用',
        ]);
        app(MstConfigRepository::class)->clearCache();
        $this->app->forgetScopedInstances();
        // scoped を破棄すると ApiSession も消えるので、プレイヤーを設定し直す
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);
        $this->service = app(StaminaService::class);
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
        DB::connection('mst')->table('mst_config')->delete();
        // 他のテストが読んだ mst_config のキャッシュ（Redis）を残さない
        app(MstConfigRepository::class)->clearCache();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('trx1')->table('trx_stamina')->delete();
    }
}
