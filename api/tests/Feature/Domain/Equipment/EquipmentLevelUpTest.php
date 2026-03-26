<?php

namespace Tests\Feature\Domain\Equipment;

use App\Domain\Equipment\UseCases\LevelUpUseCase;
use App\Utilities\ApiSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 装備レベルアップエンドポイントのテスト
 *
 * equipment/level_upエンドポイントの動作を直接テストします
 */
class EquipmentLevelUpTest extends TestCase
{
    use RefreshDatabase;

    private int $sysPlayerId = 1;
    private int $trxEquipmentId = 1;

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

    /** @test */
    public function 装備のレベルアップができる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $afterLevel = 10;
        
        // 実行前の状態確認
        $beforeEquipment = DB::connection('trx1')
            ->table('trx_equipment')
            ->where('id', $this->trxEquipmentId)
            ->first();
        
        $this->assertNotNull($beforeEquipment);
        $this->assertLessThan($afterLevel, $beforeEquipment->level);
        
        // UseCaseを実行
        $useCase = app(LevelUpUseCase::class);
        $response = $useCase->handle($this->trxEquipmentId, $afterLevel);
        
        // レスポンス確認
        $this->assertNotNull($response->trxEquipment);
        $this->assertSame($afterLevel, $response->trxEquipment->level);
        
        // データベース確認
        $afterEquipment = DB::connection('trx1')
            ->table('trx_equipment')
            ->where('id', $this->trxEquipmentId)
            ->first();
        
        $this->assertSame($afterLevel, $afterEquipment->level);
        $this->assertGreaterThan($beforeEquipment->level, $afterEquipment->level);
    }

    /** @test */
    public function レベルアップ時にアイテムが消費される(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $afterLevel = 7;
        
        // 実行前のアイテム所持数
        $beforeItem = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'equipment_exp_potion')
            ->first();
        
        $beforeItemAmount = $beforeItem ? $beforeItem->amount : 0;
        
        // UseCaseを実行
        $useCase = app(LevelUpUseCase::class);
        $response = $useCase->handle($this->trxEquipmentId, $afterLevel);
        
        // アイテム消費確認
        $this->assertNotNull($response->trxItem);
        $this->assertLessThan($beforeItemAmount, $response->trxItem->amount);
    }

    /** @test */
    public function レベルアップ時にログが記録される(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $afterLevel = 7;
        
        $beforeEquipment = DB::connection('trx1')
            ->table('trx_equipment')
            ->where('id', $this->trxEquipmentId)
            ->first();
        
        // UseCaseを実行
        $useCase = app(LevelUpUseCase::class);
        $response = $useCase->handle($this->trxEquipmentId, $afterLevel);
        
        // ログ確認
        $log = DB::connection('log1')
            ->table('log_equipment')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('trx_equipment_id', $this->trxEquipmentId)
            ->orderBy('id', 'desc')
            ->first();
        
        $this->assertNotNull($log);
        $this->assertSame($beforeEquipment->level, $log->before_level);
        $this->assertSame($afterLevel, $log->after_level);
    }

    private function insertTestData(): void
    {
        // マスターデータ
        DB::connection('mst')->table('mst_equipment')->insert([
            'id' => 'equipment_001',
            'name' => 'テスト装備',
            'max_level' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_equipment_level')->insert([
            ['mst_equipment_id' => 'equipment_001', 'level' => 1, 'required_exp' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['mst_equipment_id' => 'equipment_001', 'level' => 5, 'required_exp' => 500, 'created_at' => now(), 'updated_at' => now()],
            ['mst_equipment_id' => 'equipment_001', 'level' => 7, 'required_exp' => 700, 'created_at' => now(), 'updated_at' => now()],
            ['mst_equipment_id' => 'equipment_001', 'level' => 10, 'required_exp' => 1000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            'id' => 'equipment_exp_potion',
            'name' => '装備経験値ポーション',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // トランザクションデータ
        DB::connection('trx1')->table('trx_equipment')->insert([
            'id' => $this->trxEquipmentId,
            'sys_player_id' => $this->sysPlayerId,
            'mst_equipment_id' => 'equipment_001',
            'level' => 5,
            'level_exp' => 100,
            'grade' => 1,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'equipment_exp_potion',
            'amount' => 1000,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
