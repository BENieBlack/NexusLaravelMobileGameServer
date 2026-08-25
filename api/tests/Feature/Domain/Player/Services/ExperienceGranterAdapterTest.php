<?php

namespace Tests\Feature\Domain\Player\Services;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ExperienceGranterAdapter のテスト
 *
 * 配送やアイテム使用からの経験値付与が、付与先の種別に応じて
 * プレイヤー／ユニット／装備のレベルサービスへ振り分けられることを確認する。
 */
class ExperienceGranterAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    private int $sysPlayerId = 1;

    private int $otherPlayerId = 2;

    private int $trxUnitId = 1;

    private int $trxEquipmentId = 1;

    private ExperienceGranterInterface $granter;

    private QueryManager $queryManager;

    /** UseCaseと同じくQueryManagerで明示的に制御する */
    public function beginDatabaseTransaction(): void {}

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->granter = app(ExperienceGranterInterface::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
        $this->insertTestData();
        $this->refreshMstCache();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function プレイヤーに経験値を加算できる(): void
    {
        $this->granter->grantExperience($this->sysPlayerId, 150);
        $this->queryManager->execAllQuery();

        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(150, (int) $player->level_exp);
        // レベル2の必要累積経験値は100
        $this->assertSame(2, $player->level);
    }

    #[Test]
    public function ユニットに経験値を加算できる(): void
    {
        $this->granter->grantExperience(
            $this->sysPlayerId,
            200,
            ExperienceGranterInterface::TARGET_UNIT,
            (string) $this->trxUnitId
        );
        $this->queryManager->execAllQuery();

        $unit = DB::connection('trx1')->table('trx_unit')->where('id', $this->trxUnitId)->first();
        $this->assertSame(200, (int) $unit->level_exp);
        // SRのレベル3は累積200
        $this->assertSame(3, $unit->level);
    }

    #[Test]
    public function 装備に経験値を加算できる(): void
    {
        $this->granter->grantExperience(
            $this->sysPlayerId,
            300,
            ExperienceGranterInterface::TARGET_EQUIPMENT,
            (string) $this->trxEquipmentId
        );
        $this->queryManager->execAllQuery();

        $equipment = DB::connection('trx1')->table('trx_equipment')->where('id', $this->trxEquipmentId)->first();
        $this->assertSame(300, (int) $equipment->level_exp);
        // SRのレベル3は累積250
        $this->assertSame(3, $equipment->level);
    }

    #[Test]
    public function 他人のユニットには加算できない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit does not belong to player');

        $this->granter->grantExperience(
            $this->otherPlayerId,
            100,
            ExperienceGranterInterface::TARGET_UNIT,
            (string) $this->trxUnitId
        );
    }

    #[Test]
    public function 存在しない装備には加算できない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Equipment does not belong to player');

        $this->granter->grantExperience(
            $this->sysPlayerId,
            100,
            ExperienceGranterInterface::TARGET_EQUIPMENT,
            '999'
        );
    }

    #[Test]
    public function ユニットと装備は対象idが必須(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Target id is required for unit experience');

        $this->granter->grantExperience($this->sysPlayerId, 100, ExperienceGranterInterface::TARGET_UNIT);
    }

    #[Test]
    public function 未対応の種別は例外になる(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported experience target: guild');

        $this->granter->grantExperience($this->sysPlayerId, 100, 'guild', '1');
    }

    private function insertTestData(): void
    {
        DB::connection('mst')->table('mst_player_level')->insert([
            ['deploy_key' => self::DEPLOY_KEY, 'level' => 1, 'required_exp' => 0, 'max_stamina' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['deploy_key' => self::DEPLOY_KEY, 'level' => 2, 'required_exp' => 100, 'max_stamina' => 55, 'created_at' => now(), 'updated_at' => now()],
            ['deploy_key' => self::DEPLOY_KEY, 'level' => 3, 'required_exp' => 500, 'max_stamina' => 60, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_unit')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'id' => 'unit_001',
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_unit_level')->insert([
            ['deploy_key' => self::DEPLOY_KEY, 'rarity' => 'SR', 'level' => 1, 'required_exp' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['deploy_key' => self::DEPLOY_KEY, 'rarity' => 'SR', 'level' => 2, 'required_exp' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['deploy_key' => self::DEPLOY_KEY, 'rarity' => 'SR', 'level' => 3, 'required_exp' => 200, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_equipment')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'id' => 'equipment_001',
            'type' => 'Attack',
            'rarity' => 'SR',
            'attack' => 100,
            'defense' => 50,
            'hp' => 200,
            'sort_desc' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_equipment_level')->insert([
            ['rarity' => 'SR', 'level' => 1, 'required_exp' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 2, 'required_exp' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 3, 'required_exp' => 250, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-exp-granter',
            'my_id' => 'TEST0020',
            'name' => 'tester',
            'level' => 1,
            'level_exp' => 0,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_unit')->insert([
            'id' => $this->trxUnitId,
            'sys_player_id' => $this->sysPlayerId,
            'mst_unit_id' => 'unit_001',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_equipment')->insert([
            'id' => $this->trxEquipmentId,
            'sys_player_id' => $this->sysPlayerId,
            'mst_equipment_id' => 'equipment_001',
            'level' => 1,
            'level_exp' => 0,
            'grade' => 1,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        DB::connection('mst')->table('mst_unit_level')->delete();
        DB::connection('mst')->table('mst_equipment_level')->delete();
        DB::connection('mst')->table('mst_unit')->where('id', 'unit_001')->delete();
        DB::connection('mst')->table('mst_equipment')->where('id', 'equipment_001')->delete();
        DB::connection('sys')->table('sys_player')->whereIn('id', [$this->sysPlayerId, $this->otherPlayerId])->delete();
        DB::connection('trx1')->table('trx_unit')
            ->whereIn('sys_player_id', [$this->sysPlayerId, $this->otherPlayerId])->delete();
        DB::connection('trx1')->table('trx_equipment')
            ->whereIn('sys_player_id', [$this->sysPlayerId, $this->otherPlayerId])->delete();
    }
}
