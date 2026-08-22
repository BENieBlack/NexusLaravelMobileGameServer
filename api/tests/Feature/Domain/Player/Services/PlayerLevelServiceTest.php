<?php

namespace Tests\Feature\Domain\Player\Services;

use App\Domain\Player\Services\PlayerLevelService;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * PlayerLevelServiceのテスト
 *
 * 経験値の加算・レベルアップ判定と、
 * レベルアップ時のスタミナ全回復を確認する。
 */
class PlayerLevelServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    /** レベル => [必要累積経験値, 最大スタミナ] */
    private const LEVELS = [
        1 => [0, 50],
        2 => [100, 55],
        3 => [300, 60],
        4 => [600, 65],
    ];

    private int $sysPlayerId = 1;

    private PlayerLevelService $service;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的に制御するため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->service = app(PlayerLevelService::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
        $this->createLevelMaster();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function レベル情報を取得できる(): void
    {
        $this->createPlayer(level: 2, levelExp: 150);

        $result = $this->service->findPlayerLevel($this->sysPlayerId);

        $this->assertSame(2, $result['level']);
        $this->assertSame(150, $result['exp']);
        // レベル3に必要な累積300 - 現在150
        $this->assertSame(150, $result['exp_to_next']);
        $this->assertSame(55, $result['max_stamina']);
    }

    #[Test]
    public function 存在しないプレイヤーは例外になる(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Player not found');

        $this->service->findPlayerLevel(999);
    }

    #[Test]
    public function 経験値を加算してもレベルが上がらない場合がある(): void
    {
        $this->createPlayer(level: 1, levelExp: 0);
        $this->createStamina(currentStamina: 10);

        $result = $this->service->addExpWithStamina($this->sysPlayerId, 50);
        $this->queryManager->execAllQuery();

        $this->assertFalse($result['is_leveled_up']);
        $this->assertSame(1, $result['after_level']);
        $this->assertSame(50, $result['total_exp']);

        // スタミナは回復しない
        $stamina = $this->findStamina();
        $this->assertSame(10, $stamina->current_stamina);
    }

    #[Test]
    public function レベルアップするとスタミナが全回復する(): void
    {
        $this->createPlayer(level: 1, levelExp: 0);
        $this->createStamina(currentStamina: 10);

        $result = $this->service->addExpWithStamina($this->sysPlayerId, 100);
        $this->queryManager->execAllQuery();

        $this->assertTrue($result['is_leveled_up']);
        $this->assertSame(1, $result['before_level']);
        $this->assertSame(2, $result['after_level']);
        $this->assertSame(50, $result['before_max_stamina']);
        $this->assertSame(55, $result['after_max_stamina']);

        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(2, $player->level);
        $this->assertSame(100, (int) $player->level_exp);

        // 残っていた10に新しい最大スタミナ55が加算される（上限超過を許容する仕様）
        $stamina = $this->findStamina();
        $this->assertSame(65, $stamina->current_stamina);
    }

    #[Test]
    public function 一度に複数レベル上がる(): void
    {
        $this->createPlayer(level: 1, levelExp: 0);
        $this->createStamina(currentStamina: 0);

        $result = $this->service->addExpWithStamina($this->sysPlayerId, 600);
        $this->queryManager->execAllQuery();

        $this->assertTrue($result['is_leveled_up']);
        $this->assertSame(4, $result['after_level']);
        $this->assertSame(65, $result['after_max_stamina']);
        // 最大レベルのため次のレベルまでの必要経験値は0
        $this->assertSame(0, $result['exp_to_next']);
    }

    #[Test]
    public function スタミナレコードが無ければレベルアップ時に作成される(): void
    {
        $this->createPlayer(level: 1, levelExp: 0);

        $this->service->addExpWithStamina($this->sysPlayerId, 100);
        $this->queryManager->execAllQuery();

        $stamina = $this->findStamina();
        $this->assertNotNull($stamina);
        $this->assertSame(55, $stamina->current_stamina);
    }

    #[Test]
    public function 累積経験値からレベルを計算できる(): void
    {
        $this->assertSame(1, $this->service->calculateLevelFromExp(0));
        $this->assertSame(1, $this->service->calculateLevelFromExp(99));
        $this->assertSame(2, $this->service->calculateLevelFromExp(100));
        $this->assertSame(3, $this->service->calculateLevelFromExp(599));
        $this->assertSame(4, $this->service->calculateLevelFromExp(10000));
    }

    #[Test]
    public function 次のレベルまでの必要経験値を計算できる(): void
    {
        $this->assertSame(100, $this->service->calcExpToNextLevel(null, 1, 0));
        $this->assertSame(50, $this->service->calcExpToNextLevel(null, 1, 50));
        // 最大レベルでは0
        $this->assertSame(0, $this->service->calcExpToNextLevel(null, 4, 600));
    }

    #[Test]
    public function 最大スタミナを取得できる(): void
    {
        $this->createPlayer(level: 3, levelExp: 300);

        $this->assertSame(60, $this->service->findMaxStamina($this->sysPlayerId));
    }

    private function findStamina(): ?object
    {
        return DB::connection('trx1')->table('trx_stamina')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('type', StaminaConst::TYPE_NORMAL)
            ->first();
    }

    private function createLevelMaster(): void
    {
        $rows = [];

        foreach (self::LEVELS as $level => [$requiredExp, $maxStamina]) {
            $rows[] = [
                'deploy_key' => self::DEPLOY_KEY,
                'level' => $level,
                'required_exp' => $requiredExp,
                'max_stamina' => $maxStamina,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('mst')->table('mst_player_level')->insert($rows);
    }

    private function createPlayer(int $level, int $levelExp): void
    {
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-player-level',
            'my_id' => 'TEST0003',
            'name' => 'tester',
            'level' => $level,
            'level_exp' => $levelExp,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStamina(int $currentStamina): void
    {
        DB::connection('trx1')->table('trx_stamina')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'type' => StaminaConst::TYPE_NORMAL,
            'current_stamina' => $currentStamina,
            'recovery_rate_multiplier' => 1.00,
            'last_recovery_at' => now()->format('Y-m-d H:i:s'),
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
