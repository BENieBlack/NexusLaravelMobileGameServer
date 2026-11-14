<?php

namespace Tests\Feature\Domain\Stamina\Listeners;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Domain\Stamina\Listeners\RecoverStaminaOnPlayerLevelUp;
use App\Persistence\ApiSession;
use App\Repositories\Mst\MstConfigRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use NexusLevel\Events\PlayerLeveledUp;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * RecoverStaminaOnPlayerLevelUp のテスト
 *
 * レベルアップ時の自然回復の計算が、mst_config の回復間隔に従うことを確かめる。
 */
class RecoverStaminaOnPlayerLevelUpTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    /** レベル2の最大スタミナ */
    private const NEW_MAX_STAMINA = 60;

    private int $sysPlayerId = 1;

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
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
        DB::connection('mst')->table('mst_player_level')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'level' => 2,
            'required_exp' => 50,
            'max_stamina' => self::NEW_MAX_STAMINA,
        ]);
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-levelup-stamina',
            'my_id' => 'TEST0005',
            'name' => 'tester',
            'level' => 2,
            'level_exp' => 50,
            'vip_point' => 0,
            'total_paid_amount' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function 行が無ければ既定の回復間隔で自然回復を反映してから最大値分を加算する(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');
        // 25分前が最終回復。既定（300秒）で5ポイント。現在値 10 → 15、最大 60 を加算して 75
        $this->createStamina(10, '2026-03-15 11:35:00');
        $this->assertSame(300, MstConfigKey::StaminaRecoveryIntervalSeconds->defaultInt());

        $this->fire();

        $row = $this->findStaminaRow();
        $this->assertSame(75, $row->current_stamina);
        $this->assertSame('2026-03-15 12:00:00', (string) $row->last_recovery_at);
    }

    #[Test]
    public function mst_configの回復間隔に従って自然回復を反映する(): void
    {
        DB::connection('mst')->table('mst_config')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'id' => MstConfigKey::StaminaRecoveryIntervalSeconds->value,
            'value' => '600',
            'value_type' => 'int',
            'description' => 'テスト用',
        ]);
        app(MstConfigRepository::class)->clearCache();
        $this->app->forgetScopedInstances();
        // scoped を破棄すると ApiSession も消えるので、プレイヤーを設定し直す
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);
        // QueryManager も scoped なので取り直す
        $this->queryManager = app(QueryManager::class);

        ClockUtility::setNow('2026-03-15 12:00:00');
        // 25分前が最終回復。600秒で2ポイント。現在値 10 → 12、最大 60 を加算して 72
        $this->createStamina(10, '2026-03-15 11:35:00');

        $this->fire();

        $row = $this->findStaminaRow();
        $this->assertSame(72, $row->current_stamina);
        // 2 × 600 秒 = 20 分だけ進む（残り 5 分は持ち越し）
        $this->assertSame('2026-03-15 11:55:00', (string) $row->last_recovery_at);
    }

    private function fire(): void
    {
        app(RecoverStaminaOnPlayerLevelUp::class)->handle(new PlayerLeveledUp($this->sysPlayerId, 1, 2));
        $this->queryManager->execAllQuery();
    }

    private function findStaminaRow(): object
    {
        return DB::connection('trx1')->table('trx_stamina')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('type', StaminaConst::TYPE_NORMAL)
            ->first();
    }

    private function createStamina(int $currentStamina, string $lastRecoveryAt): void
    {
        DB::connection('trx1')->table('trx_stamina')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'type' => StaminaConst::TYPE_NORMAL,
            'current_stamina' => $currentStamina,
            'recovery_rate_multiplier' => 1.0,
            'last_recovery_at' => $lastRecoveryAt,
            'created_at' => ClockUtility::nowToString(),
            'updated_at' => ClockUtility::nowToString(),
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        DB::connection('mst')->table('mst_config')->delete();
        app(MstConfigRepository::class)->clearCache();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('trx1')->table('trx_stamina')->delete();
    }
}
