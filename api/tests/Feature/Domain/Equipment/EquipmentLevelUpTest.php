<?php

namespace Tests\Feature\Domain\Equipment;

use App\Domain\Equipment\UseCases\LevelUpUseCase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 装備レベルアップエンドポイントのテスト
 *
 * equipment/level_upエンドポイントの動作を直接テストします
 */
class EquipmentLevelUpTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;
    private int $trxEquipmentId = 1;

    /**
     * Override to prevent automatic transaction wrapping
     * because UseCases manage their own transactions
     */
    public function beginDatabaseTransaction(): void
    {
        // Do nothing - let the UseCase handle transactions
    }

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
        $response = $useCase->handle($this->sysPlayerId, $this->trxEquipmentId, $afterLevel);
        
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

    #[Test]
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
        $response = $useCase->handle($this->sysPlayerId, $this->trxEquipmentId, $afterLevel);
        
        // アイテム消費確認
        $this->assertNotNull($response->trxItem);
        $this->assertLessThan($beforeItemAmount, $response->trxItem->amount);
    }

    #[Test]
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
        $response = $useCase->handle($this->sysPlayerId, $this->trxEquipmentId, $afterLevel);
        
        // ログ確認
        $log = DB::connection('log')
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
        // 既存データをクリア（テスト間でクリーンな状態を保つため）
        DB::connection('trx1')->table('trx_equipment')->where('id', $this->trxEquipmentId)->delete();
        DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('mst')->table('mst_equipment__l10n')->where('mst_equipment_id', 'equipment_001')->delete();
        DB::connection('mst')->table('mst_equipment')->where('id', 'equipment_001')->delete();
        DB::connection('mst')->table('mst_equipment_level')->where('rarity', 'SR')->delete();
        DB::connection('mst')->table('mst_item__l10n')->where('mst_item_id', 'equipment_exp_potion')->delete();
        DB::connection('mst')->table('mst_item')->where('id', 'equipment_exp_potion')->delete();
        
        // マスターデータ
        DB::connection('mst')->table('mst_equipment')->insert([
            'id' => 'equipment_001',
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SR',
            'attack' => 100,
            'defense' => 50,
            'hp' => 200,
            'sort_desc' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 装備名の多言語データ
        DB::connection('mst')->table('mst_equipment__l10n')->insert([
            'mst_equipment_id' => 'equipment_001',
            'language' => 'ja',
            'name' => 'テスト装備',
            'description' => 'テスト用の装備です',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_equipment_level')->insert([
            ['rarity' => 'SR', 'level' => 1, 'required_exp' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 2, 'required_exp' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 3, 'required_exp' => 250, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 4, 'required_exp' => 400, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 5, 'required_exp' => 600, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 6, 'required_exp' => 800, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 7, 'required_exp' => 1050, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 8, 'required_exp' => 1300, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 9, 'required_exp' => 1600, 'created_at' => now(), 'updated_at' => now()],
            ['rarity' => 'SR', 'level' => 10, 'required_exp' => 2000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            'id' => 'equipment_exp_potion',
            'type' => 'EquipmentEnhancement',
            'effect' => 'EquipmentExp',
            'value' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // アイテム名の多言語データ
        DB::connection('mst')->table('mst_item__l10n')->insert([
            'mst_item_id' => 'equipment_exp_potion',
            'language' => 'ja',
            'name' => '装備経験値ポーション',
            'description' => '装備の経験値を増やすポーション',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // トランザクションデータ
        DB::connection('trx1')->table('trx_equipment')->insert([
            'id' => $this->trxEquipmentId,
            'sys_player_id' => $this->sysPlayerId,
            'mst_equipment_id' => 'equipment_001',
            'level' => 5,
            'level_exp' => 600, // Level 5に必要な累積経験値
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
