<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxEquipmentRepository;
use Tests\RefreshMultipleDatabases;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
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
    public function delete_modelで論理削除できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();
        $equipmentToDelete = $equipments->get(1);

        $this->assertNotNull($equipmentToDelete);
        $this->assertFalse((bool) $equipmentToDelete->is_delete);

        $reflection = new \ReflectionClass($repo);
        $deleteModelMethod = $reflection->getMethod('deleteModel');
        $deleteModelMethod->setAccessible(true);
        $deleteModelMethod->invoke($repo, $equipmentToDelete);

        $this->assertTrue((bool) $equipmentToDelete->is_delete);

        // キャッシュから確認
        $cached = $repo->queryOrMemory()->where('id', $equipmentToDelete->id)->first();
        $this->assertTrue((bool) $cached->is_delete);

        // modelQueueに追加されたか確認
        $modelQueueProperty = $reflection->getProperty('modelQueue');
        $modelQueueProperty->setAccessible(true);
        $modelQueue = $modelQueueProperty->getValue($repo);

        $this->assertGreaterThan(0, count($modelQueue));
    }

    #[Test]
    public function terminateで物理削除準備ができる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();
        $equipmentToTerminate = $equipments->last();

        $this->assertNotNull($equipmentToTerminate);

        // まず論理削除マークを設定
        $equipmentToTerminate->is_delete = true;
        $reflection = new \ReflectionClass($repo);
        $setModelMethod = $reflection->getMethod('setModel');
        $setModelMethod->setAccessible(true);
        $setModelMethod->invoke($repo, $equipmentToTerminate);

        // terminate()を呼び出してdeleteQueueに追加
        $terminateMethod = $reflection->getMethod('terminate');
        $terminateMethod->setAccessible(true);
        $terminateMethod->invoke($repo, $equipmentToTerminate);

        // deleteQueueに追加されたか確認
        $deleteQueueProperty = $reflection->getProperty('deleteQueue');
        $deleteQueueProperty->setAccessible(true);
        $deleteQueue = $deleteQueueProperty->getValue($repo);

        $this->assertGreaterThan(0, count($deleteQueue));
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
