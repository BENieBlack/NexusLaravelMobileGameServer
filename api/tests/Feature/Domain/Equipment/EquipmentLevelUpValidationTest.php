<?php

namespace Tests\Feature\Domain\Equipment;

use App\Domain\Equipment\Services\EquipmentLevelService;
use App\Domain\Equipment\UseCases\LevelUpUseCase;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameException;
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 装備レベルアップの弾かれ方のテスト
 *
 * 正常系は EquipmentLevelUpTest が見ている。ここは弾かれる側で、
 * 「素材だけ消えて何も上がらない」事故が起きないことを確かめる。
 *
 * 装備は目標レベルを指定する方式で、必要なアイテム数は
 * 目標までの必要経験値から逆算される。
 */
class EquipmentLevelUpValidationTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private int $otherPlayerId = 2;

    private int $trxEquipmentId = 1;

    private int $otherTrxEquipmentId = 2;

    /** 装備経験値アイテム（mst_item.effect = EquipmentExp） */
    private const EXP_ITEM_ID = 'equipment_exp_potion';

    /** 効果種別が違うアイテム */
    private const WRONG_ITEM_ID = 'unit_exp_potion';

    /** 経験値が0のアイテム（必要個数を割り出せない） */
    private const ZERO_EXP_ITEM_ID = 'broken_potion';

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->insertTestData();
        ApiSession::setSysPlayerId($this->sysPlayerId);
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestData();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 存在しない装備は弾かれる(): void
    {
        $this->expectException(TransactionDataException::class);

        app(LevelUpUseCase::class)->exec($this->sysPlayerId, 999999, self::EXP_ITEM_ID, 10);
    }

    #[Test]
    public function 他人の装備は育てられない(): void
    {
        // TrxEquipmentRepository はログイン中プレイヤーの行しか読まないため、
        // 他人の装備はそもそも見えず「存在しない」として弾かれる
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->otherTrxEquipmentId, self::EXP_ITEM_ID, 10);
            $this->fail('他人の装備にレベルアップできてしまった');
        } catch (GameException) {
            // 期待どおり
        }

        $this->assertSame($before, $this->findItemAmount(self::EXP_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(1, $this->findEquipment($this->otherTrxEquipmentId)->level);
    }

    #[Test]
    public function 現在レベル以下は目標にできない(): void
    {
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        foreach ([4, 5] as $targetLevel) {
            try {
                app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, self::EXP_ITEM_ID, $targetLevel);
                $this->fail("レベル{$targetLevel}を目標にできてしまった");
            } catch (GameException $e) {
                $this->assertStringContainsString('Target level must be greater than current level', $e->getMessage());
            }
        }

        $this->assertSame($before, $this->findItemAmount(self::EXP_ITEM_ID), 'アイテムは減らない');
    }

    #[Test]
    public function 存在しないアイテムは弾かれる(): void
    {
        $this->expectException(MasterDataException::class);

        app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, 'no_such_item', 10);
    }

    #[Test]
    public function 効果種別が違うアイテムは使えない(): void
    {
        // 判定は type ではなく effect で行う。
        // ユニット用の経験値アイテムで装備を育てられてはいけない
        $before = $this->findItemAmount(self::WRONG_ITEM_ID);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, self::WRONG_ITEM_ID, 10);
            $this->fail('効果種別が違うアイテムで育ってしまった');
        } catch (BusinessLogicException $e) {
            $this->assertStringContainsString('equipment_exp', $e->getMessage());
        }

        $this->assertSame($before, $this->findItemAmount(self::WRONG_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(5, $this->findEquipment()->level);
    }

    #[Test]
    public function 経験値が0のアイテムは必要個数を割り出せないので弾く(): void
    {
        // ゼロ除算になる前に落とす
        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, self::ZERO_EXP_ITEM_ID, 10);
            $this->fail('経験値0のアイテムが通ってしまった');
        } catch (GameException $e) {
            $this->assertStringContainsString('Invalid exp value for item', $e->getMessage());
        }

        $this->assertSame(5, $this->findEquipment()->level);
    }

    #[Test]
    public function 必要な個数を持っていなければ弾かれる(): void
    {
        // level 5（累積600）から level 10（2000）まで 1400exp、100expのアイテムが14個要る
        DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::EXP_ITEM_ID)
            ->update(['free_amount' => 13]);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, self::EXP_ITEM_ID, 10);
            $this->fail('個数が足りないのに通ってしまった');
        } catch (BusinessLogicException $e) {
            $this->assertStringContainsString(self::EXP_ITEM_ID, $e->getMessage());
        }

        $this->assertSame(13, $this->findItemAmount(self::EXP_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(5, $this->findEquipment()->level, 'レベルも上がらない');
    }

    #[Test]
    public function ちょうどの個数なら通る(): void
    {
        DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::EXP_ITEM_ID)
            ->update(['free_amount' => 14]);

        app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxEquipmentId, self::EXP_ITEM_ID, 10);

        $this->assertSame(10, $this->findEquipment()->level);
    }

    // ========================================
    // EquipmentLevelService
    // ========================================

    #[Test]
    public function レベル情報を取得できる(): void
    {
        $level = app(EquipmentLevelService::class)->findEquipmentLevel($this->trxEquipmentId);

        $this->assertSame(5, $level['level']);
        $this->assertSame(600, $level['exp']);
        $this->assertSame(200, $level['exp_to_next'], 'level 6 の800まで残り200');
        $this->assertSame('SR', $level['rarity']);
        $this->assertSame(10, $level['max_level']);
    }

    #[Test]
    public function 最大レベルの装備は次のレベルまでがnull(): void
    {
        DB::connection('trx1')->table('trx_equipment')
            ->where('id', $this->trxEquipmentId)
            ->update(['level' => 10, 'level_exp' => 2000]);

        $this->assertNull(app(EquipmentLevelService::class)->findEquipmentLevel($this->trxEquipmentId)['exp_to_next']);
    }

    #[Test]
    public function 存在しない装備のレベル情報は取れない(): void
    {
        $this->expectException(TransactionDataException::class);

        app(EquipmentLevelService::class)->findEquipmentLevel(999999);
    }

    #[Test]
    public function 目標レベルまでの必要経験値を出せる(): void
    {
        $service = app(EquipmentLevelService::class);

        // level 5（累積600）から level 10（2000）まで
        $this->assertSame(1400, $service->calculateRequiredExp($this->trxEquipmentId, 10));
        $this->assertSame(200, $service->calculateRequiredExp($this->trxEquipmentId, 6));
    }

    #[Test]
    public function 既に到達しているレベルの必要経験値は0(): void
    {
        $this->assertSame(0, app(EquipmentLevelService::class)->calculateRequiredExp($this->trxEquipmentId, 3));
    }

    #[Test]
    public function 定義の無いレベルは必要経験値を出せない(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Target level 99 does not exist for rarity SR');

        app(EquipmentLevelService::class)->calculateRequiredExp($this->trxEquipmentId, 99);
    }

    #[Test]
    public function 存在しない装備の必要経験値は出せない(): void
    {
        $this->expectException(TransactionDataException::class);

        app(EquipmentLevelService::class)->calculateRequiredExp(999999, 10);
    }

    #[Test]
    public function 経験値を加算して更新後の装備を返せる(): void
    {
        $equipment = app(EquipmentLevelService::class)->addExpAndReturn($this->trxEquipmentId, 250);

        $this->assertSame(6, $equipment->getLevel(), '600 + 250 = 850 は level 6');
        $this->assertSame(850, $equipment->getLevelExp());
    }

    #[Test]
    public function 累積経験値からレベルを逆算できる(): void
    {
        $service = app(EquipmentLevelService::class);

        $this->assertSame(1, $service->calculateLevelFromExp('SR', 0));
        $this->assertSame(5, $service->calculateLevelFromExp('SR', 600));
        $this->assertSame(5, $service->calculateLevelFromExp('SR', 799), '次の閾値に届くまでは上がらない');
        $this->assertSame(6, $service->calculateLevelFromExp('SR', 800));
    }

    private function findEquipment(?int $trxEquipmentId = null): object
    {
        $row = DB::connection('trx1')->table('trx_equipment')
            ->where('id', $trxEquipmentId ?? $this->trxEquipmentId)
            ->first();

        $this->assertNotNull($row);

        return $row;
    }

    private function findItemAmount(string $mstItemId): int
    {
        $row = DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function cleanUpTestData(): void
    {
        DB::connection('trx1')->table('trx_equipment')
            ->whereIn('id', [$this->trxEquipmentId, $this->otherTrxEquipmentId])->delete();
        DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('mst')->table('mst_equipment__l10n')->where('mst_equipment_id', 'equipment_001')->delete();
        DB::connection('mst')->table('mst_equipment')->where('id', 'equipment_001')->delete();
        DB::connection('mst')->table('mst_equipment_level')->where('rarity', 'SR')->delete();
        DB::connection('mst')->table('mst_item__l10n')
            ->whereIn('mst_item_id', [self::EXP_ITEM_ID, self::WRONG_ITEM_ID, self::ZERO_EXP_ITEM_ID])->delete();
        DB::connection('mst')->table('mst_item')
            ->whereIn('id', [self::EXP_ITEM_ID, self::WRONG_ITEM_ID, self::ZERO_EXP_ITEM_ID])->delete();

        // 入れたマスターをキャッシュに残さない
        $this->refreshMstCache();
    }

    private function insertTestData(): void
    {
        $this->cleanUpTestData();

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

        DB::connection('mst')->table('mst_equipment__l10n')->insert([
            'mst_equipment_id' => 'equipment_001',
            'language' => 'ja',
            'name' => 'テスト装備',
            'description' => 'テスト用の装備です',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levels = [1 => 0, 2 => 100, 3 => 250, 4 => 400, 5 => 600, 6 => 800, 7 => 1050, 8 => 1300, 9 => 1600, 10 => 2000];
        DB::connection('mst')->table('mst_equipment_level')->insert(
            array_map(
                fn (int $level, int $requiredExp) => [
                    'rarity' => 'SR',
                    'level' => $level,
                    'required_exp' => $requiredExp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                array_keys($levels),
                array_values($levels)
            )
        );

        DB::connection('mst')->table('mst_item')->insert([
            [
                'id' => self::EXP_ITEM_ID,
                'type' => 'EquipmentEnhancement',
                'effect' => 'equipment_exp',
                'value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::WRONG_ITEM_ID,
                'type' => 'EquipmentEnhancement',
                'effect' => 'unit_exp',
                'value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::ZERO_EXP_ITEM_ID,
                'type' => 'EquipmentEnhancement',
                'effect' => 'equipment_exp',
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([self::EXP_ITEM_ID, self::WRONG_ITEM_ID, self::ZERO_EXP_ITEM_ID] as $mstItemId) {
            DB::connection('mst')->table('mst_item__l10n')->insert([
                'mst_item_id' => $mstItemId,
                'language' => 'ja',
                'name' => "テストアイテム {$mstItemId}",
                'description' => 'テスト用のアイテムです',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('trx1')->table('trx_equipment')->insert([
            [
                'id' => $this->trxEquipmentId,
                'sys_player_id' => $this->sysPlayerId,
                'mst_equipment_id' => 'equipment_001',
                'level' => 5,
                'level_exp' => 600,
                'grade' => 1,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->otherTrxEquipmentId,
                'sys_player_id' => $this->otherPlayerId,
                'mst_equipment_id' => 'equipment_001',
                'level' => 1,
                'level_exp' => 0,
                'grade' => 1,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([self::EXP_ITEM_ID, self::WRONG_ITEM_ID, self::ZERO_EXP_ITEM_ID] as $mstItemId) {
            DB::connection('trx1')->table('trx_item')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => $mstItemId,
                'free_amount' => 1000,
                'paid_amount' => 0,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
