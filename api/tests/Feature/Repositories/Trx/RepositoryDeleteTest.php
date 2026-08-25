<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxEquipmentRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 削除機能のテスト
 */
class RepositoryDeleteTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        ApiSession::clearForTest();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    #[Test]
    public function set_modelでモデルを更新できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipment = $repo->queryOrMemory()->first();
        $this->assertNotNull($equipment);

        $originalLevel = $equipment->level;
        $equipment->level = $originalLevel + 5;

        $reflection = new \ReflectionClass($repo);
        $setModelMethod = $reflection->getMethod('setModel');
        $setModelMethod->setAccessible(true);
        $setModelMethod->invoke($repo, $equipment);

        // キャッシュから取得して確認
        $cached = $repo->queryOrMemory()->where('id', $equipment->id)->first();

        $this->assertSame($equipment->level, $cached->level);

        // modelQueueに追加されたか確認
        $modelQueueProperty = $reflection->getProperty('modelQueue');
        $modelQueueProperty->setAccessible(true);
        $modelQueue = $modelQueueProperty->getValue($repo);

        $this->assertGreaterThan(0, count($modelQueue));
    }

    #[Test]
    public function soft_delete_modelで論理削除できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();
        // queryOrMemory()はユニークキー（id）でキーイングされるため、
        // キー指定だとauto_incrementの採番に依存してしまう。並び順で取る
        $equipmentToDelete = $equipments->values()->get(1);

        $this->assertNotNull($equipmentToDelete);
        $this->assertFalse((bool) $equipmentToDelete->is_delete);

        $repo->softDeleteModel($equipmentToDelete);

        $this->assertTrue((bool) $equipmentToDelete->is_delete);

        // キャッシュから確認
        $cached = $repo->queryOrMemory()->where('id', $equipmentToDelete->id)->first();
        $this->assertTrue((bool) $cached->is_delete);

        // 論理削除はUPDATEなのでmodelQueueに積まれる
        $reflection = new \ReflectionClass($repo);
        $modelQueueProperty = $reflection->getProperty('modelQueue');
        $modelQueueProperty->setAccessible(true);

        $this->assertGreaterThan(0, count($modelQueueProperty->getValue($repo)));

        // deleteQueueには積まれない
        $deleteQueueProperty = $reflection->getProperty('deleteQueue');
        $deleteQueueProperty->setAccessible(true);

        $this->assertCount(0, $deleteQueueProperty->getValue($repo));
    }

    #[Test]
    public function hard_delete_modelで物理削除キューに積まれる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();
        $equipmentToDelete = $equipments->last();

        $this->assertNotNull($equipmentToDelete);

        $repo->hardDeleteModel($equipmentToDelete);

        $reflection = new \ReflectionClass($repo);

        // 物理削除はdeleteQueueに積まれる
        $deleteQueueProperty = $reflection->getProperty('deleteQueue');
        $deleteQueueProperty->setAccessible(true);

        $this->assertGreaterThan(0, count($deleteQueueProperty->getValue($repo)));

        // is_deleteフラグは立てない
        $this->assertFalse((bool) $equipmentToDelete->is_delete);
    }

    #[Test]
    public function hard_delete_modelで_d_bから行が消える(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipmentToDelete = $repo->queryOrMemory()->last();
        $this->assertNotNull($equipmentToDelete);
        $deletedId = $equipmentToDelete->id;

        $repo->hardDeleteModel($equipmentToDelete);
        $this->flushQueue();

        $this->assertDatabaseMissing(
            'trx_equipment',
            ['id' => $deletedId],
            'trx1'
        );
    }

    private function insertTestData(): void
    {
        DB::connection('trx1')->table('trx_equipment')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_equipment_id' => 'equipment_001',
                'level' => 5,
                'level_exp' => 100,
                'grade' => 1,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_equipment_id' => 'equipment_002',
                'level' => 10,
                'level_exp' => 200,
                'grade' => 2,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_equipment_id' => 'equipment_003',
                'level' => 1,
                'level_exp' => 0,
                'grade' => 1,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
