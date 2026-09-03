<?php

namespace Tests\Feature\Domain\Unit;

use App\Domain\Unit\Services\UnitLevelService;
use App\Domain\Unit\UseCases\LevelUpUseCase;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameException;
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Http\Responses\Unit\LevelUpResponse;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ユニットのレベルアップのテスト
 *
 * 経験値アイテムを消費してレベルを上げる。
 * 「素材だけ消えてレベルが上がらない」「他人のユニットを育てられる」
 * といった事故が起きないことが要点。
 *
 * 装備は目標レベルを指定する方式、ユニットは使用個数を指定する方式で、
 * 引数の意味が違う点に注意。
 */
class UnitLevelUpTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private int $otherPlayerId = 2;

    private int $trxUnitId = 1;

    private int $otherTrxUnitId = 2;

    /** ユニット経験値アイテム（mst_item.effect = UnitExp） */
    private const EXP_ITEM_ID = 'unit_exp_potion';

    /** スタミナ回復アイテム（効果種別が違うので弾かれる） */
    private const WRONG_ITEM_ID = 'stamina_potion';

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

    // ========================================
    // 正常系
    // ========================================

    #[Test]
    public function アイテムを使ってレベルが上がる(): void
    {
        // level 5（累積600）から、100expのアイテムを3個 = 300exp → 累積900 → level 6
        $response = $this->levelUp(useCount: 3);

        $this->assertTrue($response->isLeveledUp);
        $this->assertSame(5, $response->beforeLevel);
        $this->assertSame(6, $response->afterLevel);
        $this->assertSame(900, $response->totalExp);
        $this->assertSame('SR', $response->rarity);
        $this->assertSame(10, $response->maxLevel);
        $this->assertSame(3, $response->itemUsed);
        $this->assertSame(300, $response->expGained);

        $this->assertSame(6, $this->findUnit()->level);
        $this->assertSame(900, (int) $this->findUnit()->level_exp);
    }

    #[Test]
    public function 使った分だけアイテムが減る(): void
    {
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        $this->levelUp(useCount: 3);

        $this->assertSame($before - 3, $this->findItemAmount(self::EXP_ITEM_ID));
    }

    #[Test]
    public function 経験値が足りなければレベルは据え置きで経験値だけ増える(): void
    {
        // level 5（累積600）に100exp足しても level 6（800）には届かない
        $response = $this->levelUp(useCount: 1);

        $this->assertFalse($response->isLeveledUp);
        $this->assertSame(5, $response->beforeLevel);
        $this->assertSame(5, $response->afterLevel);
        $this->assertSame(700, $response->totalExp);
        $this->assertSame(100, $response->expToNext, '次のレベルまで残り100');
    }

    #[Test]
    public function 最大レベルを超える経験値でも最大レベルで止まる(): void
    {
        // 100exp × 50個 = 5000exp。level 10（2000）を大きく超える
        $response = $this->levelUp(useCount: 50);

        $this->assertSame(10, $response->afterLevel);
        $this->assertSame(5600, $response->totalExp, '経験値そのものは加算される');
        $this->assertSame(5600, (int) $this->findUnit()->level_exp);
        $this->assertNull($response->expToNext, '最大レベルなので次は無い');
    }

    // ========================================
    // 異常系（何も減らないこと）
    // ========================================

    #[Test]
    public function 他人のユニットは育てられない(): void
    {
        // TrxUnitRepository は queryOrMemory() でログイン中プレイヤーの行しか読まないため、
        // 他人のユニットはそもそも見えず「存在しない」として弾かれる。
        // UseCase側の所有者チェックはこの経路では通らない二重の防御
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->otherTrxUnitId, self::EXP_ITEM_ID, 1);
            $this->fail('他人のユニットにレベルアップできてしまった');
        } catch (GameException $e) {
            $this->assertSame("Unit not found: {$this->otherTrxUnitId}", $e->getMessage());
        }

        $this->assertSame($before, $this->findItemAmount(self::EXP_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(1, $this->findUnit($this->otherTrxUnitId)->level, '他人のユニットも変わらない');
    }

    #[Test]
    public function 存在しないユニットは弾かれる(): void
    {
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        $this->expectException(TransactionDataException::class);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, 999999, self::EXP_ITEM_ID, 1);
        } finally {
            $this->assertSame($before, $this->findItemAmount(self::EXP_ITEM_ID));
        }
    }

    #[Test]
    public function 存在しないアイテムは弾かれる(): void
    {
        $this->expectException(MasterDataException::class);

        app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxUnitId, 'no_such_item', 1);
    }

    #[Test]
    public function 効果種別が違うアイテムは使えない(): void
    {
        // 判定は type ではなく effect で行う。
        // スタミナ回復アイテムでユニットを育てられてはいけない
        $before = $this->findItemAmount(self::WRONG_ITEM_ID);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxUnitId, self::WRONG_ITEM_ID, 1);
            $this->fail('効果種別が違うアイテムで育ってしまった');
        } catch (BusinessLogicException $e) {
            $this->assertStringContainsString('unit_exp', $e->getMessage());
        }

        $this->assertSame($before, $this->findItemAmount(self::WRONG_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(5, $this->findUnit()->level);
    }

    #[Test]
    public function 所持数を超えて使うことはできない(): void
    {
        $before = $this->findItemAmount(self::EXP_ITEM_ID);

        try {
            app(LevelUpUseCase::class)->exec($this->sysPlayerId, $this->trxUnitId, self::EXP_ITEM_ID, $before + 1);
            $this->fail('所持数を超えて使えてしまった');
        } catch (BusinessLogicException $e) {
            $this->assertStringContainsString(self::EXP_ITEM_ID, $e->getMessage());
        }

        $this->assertSame($before, $this->findItemAmount(self::EXP_ITEM_ID), 'アイテムは減らない');
        $this->assertSame(5, $this->findUnit()->level, 'レベルも上がらない');
    }

    // ========================================
    // UnitLevelService
    // ========================================

    #[Test]
    public function レベル情報を取得できる(): void
    {
        $level = app(UnitLevelService::class)->findUnitLevel($this->trxUnitId);

        $this->assertSame(5, $level['level']);
        $this->assertSame(600, $level['exp']);
        $this->assertSame(200, $level['exp_to_next'], 'level 6 の800まで残り200');
        $this->assertSame('SR', $level['rarity']);
        $this->assertSame(10, $level['max_level']);
    }

    #[Test]
    public function 最大レベルのユニットは次のレベルまでがnull(): void
    {
        DB::connection('trx1')->table('trx_unit')
            ->where('id', $this->trxUnitId)
            ->update(['level' => 10, 'level_exp' => 2000]);

        $level = app(UnitLevelService::class)->findUnitLevel($this->trxUnitId);

        $this->assertSame(10, $level['level']);
        $this->assertNull($level['exp_to_next']);
    }

    #[Test]
    public function 存在しないユニットのレベル情報は取れない(): void
    {
        $this->expectException(TransactionDataException::class);

        app(UnitLevelService::class)->findUnitLevel(999999);
    }

    #[Test]
    public function 次のレベルまでの残り経験値は0未満にならない(): void
    {
        $service = app(UnitLevelService::class);

        // level 6 に必要な800を既に超えて持っている
        $this->assertSame(0, $service->calcExpToNextLevel('SR', 5, 900));
    }

    #[Test]
    public function 定義の無いレアリティは次のレベルが無い扱い(): void
    {
        $this->assertNull(app(UnitLevelService::class)->calcExpToNextLevel('UR', 1, 0));
    }

    private function levelUp(int $useCount): LevelUpResponse
    {
        return app(LevelUpUseCase::class)->exec(
            $this->sysPlayerId,
            $this->trxUnitId,
            self::EXP_ITEM_ID,
            $useCount
        );
    }

    private function findUnit(?int $trxUnitId = null): object
    {
        $row = DB::connection('trx1')->table('trx_unit')
            ->where('id', $trxUnitId ?? $this->trxUnitId)
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
        DB::connection('trx1')->table('trx_unit')->whereIn('id', [$this->trxUnitId, $this->otherTrxUnitId])->delete();
        DB::connection('trx1')->table('trx_item')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('mst')->table('mst_unit__l10n')->where('mst_unit_id', 'unit_001')->delete();
        DB::connection('mst')->table('mst_unit')->where('id', 'unit_001')->delete();
        DB::connection('mst')->table('mst_unit_level')->where('rarity', 'SR')->delete();
        DB::connection('mst')->table('mst_item__l10n')
            ->whereIn('mst_item_id', [self::EXP_ITEM_ID, self::WRONG_ITEM_ID])->delete();
        DB::connection('mst')->table('mst_item')
            ->whereIn('id', [self::EXP_ITEM_ID, self::WRONG_ITEM_ID])->delete();

        // 入れたマスターをキャッシュに残さない
        $this->refreshMstCache();
    }

    private function insertTestData(): void
    {
        $this->cleanUpTestData();

        DB::connection('mst')->table('mst_unit')->insert([
            'id' => 'unit_001',
            'type' => 'Attack',
            'element' => 'Fire',
            'rarity' => 'SR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_unit__l10n')->insert([
            'mst_unit_id' => 'unit_001',
            'language' => 'ja',
            'name' => 'テストユニット',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levels = [1 => 0, 2 => 100, 3 => 250, 4 => 400, 5 => 600, 6 => 800, 7 => 1050, 8 => 1300, 9 => 1600, 10 => 2000];
        DB::connection('mst')->table('mst_unit_level')->insert(
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
                'type' => 'UnitEnhancement',
                'effect' => 'unit_exp',
                'value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::WRONG_ITEM_ID,
                'type' => 'UnitEnhancement',
                'effect' => 'stamina_recover',
                'value' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('mst')->table('mst_item__l10n')->insert([
            [
                'mst_item_id' => self::EXP_ITEM_ID,
                'language' => 'ja',
                'name' => 'ユニット経験値ポーション',
                'description' => 'ユニットの経験値を増やすポーション',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mst_item_id' => self::WRONG_ITEM_ID,
                'language' => 'ja',
                'name' => 'スタミナポーション',
                'description' => 'スタミナを回復するポーション',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('trx1')->table('trx_unit')->insert([
            [
                'id' => $this->trxUnitId,
                'sys_player_id' => $this->sysPlayerId,
                'mst_unit_id' => 'unit_001',
                'grade' => 1,
                'level' => 5,
                'level_exp' => 600,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->otherTrxUnitId,
                'sys_player_id' => $this->otherPlayerId,
                'mst_unit_id' => 'unit_001',
                'grade' => 1,
                'level' => 1,
                'level_exp' => 0,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('trx1')->table('trx_item')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => self::EXP_ITEM_ID,
                'free_amount' => 1000,
                'paid_amount' => 0,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => self::WRONG_ITEM_ID,
                'free_amount' => 10,
                'paid_amount' => 0,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
