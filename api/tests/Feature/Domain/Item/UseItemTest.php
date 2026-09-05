<?php

namespace Tests\Feature\Domain\Item;

use App\Domain\Item\UseCases\UseItemUseCase;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Exceptions\GameException;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * アイテム使用のテスト
 *
 * mst_item.effect で効果種別、mst_item.value で1個あたりの効果量を定義し、
 * 使用時にその分だけ適用されることを確認する。
 */
class UseItemTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const PLAYER_EXP_ITEM_ID = 'player_exp_potion';

    private const STAMINA_ITEM_ID = 'stamina_potion';

    private const UNIT_EXP_ITEM_ID = 'unit_exp_potion_for_use_test';

    private int $sysPlayerId = 1;

    private UseItemUseCase $useCase;

    /** UseCaseが自身でトランザクションを制御するため自動ラップしない */
    public function beginDatabaseTransaction(): void {}

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        $this->useCase = app(UseItemUseCase::class);

        $this->cleanUp();
        $this->insertTestData();

        // マスタはリポジトリがメモリキャッシュするため、投入後にクリアする
        $this->refreshMstCache();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function プレイヤー経験値アイテムを使うと効果量分の経験値が入る(): void
    {
        // value=100 のアイテムを3個 → 300exp
        $response = $this->useCase->exec($this->sysPlayerId, self::PLAYER_EXP_ITEM_ID, 3);

        $this->assertSame('player_exp', $response->effect);
        $this->assertSame(3, $response->itemUsed);
        $this->assertSame(300, $response->appliedValue);

        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(300, (int) $player->level_exp);
        // レベル2の必要累積経験値は100なのでレベルアップしている
        $this->assertSame(2, $player->level);
    }

    #[Test]
    public function 使った分だけアイテムが減る(): void
    {
        $this->useCase->exec($this->sysPlayerId, self::PLAYER_EXP_ITEM_ID, 3);

        $item = DB::connection('trx1')->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::PLAYER_EXP_ITEM_ID)
            ->first();

        $this->assertSame(7, (int) $item->free_amount);
    }

    #[Test]
    public function スタミナ回復アイテムを使うと効果量分回復する(): void
    {
        $response = $this->useCase->exec($this->sysPlayerId, self::STAMINA_ITEM_ID, 2);

        $this->assertSame('stamina_recover', $response->effect);
        $this->assertSame(60, $response->appliedValue);

        $stamina = DB::connection('trx1')->table('trx_stamina')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('type', StaminaConst::TYPE_NORMAL)
            ->first();

        // 10 + 30×2
        $this->assertSame(70, (int) $stamina->current_stamina);
    }

    #[Test]
    public function 対象の指定が要る効果はこのエンドポイントでは使えない(): void
    {
        // ユニット経験値は「どのユニットに使うか」が要るため /unit/level_up 側で扱う
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid item type');

        $this->useCase->exec($this->sysPlayerId, self::UNIT_EXP_ITEM_ID, 1);
    }

    #[Test]
    public function 所持数が足りなければ使えない(): void
    {
        $this->expectException(GameException::class);

        $this->useCase->exec($this->sysPlayerId, self::PLAYER_EXP_ITEM_ID, 999);
    }

    #[Test]
    public function 存在しないアイテムは使えない(): void
    {
        $this->expectException(GameException::class);

        $this->useCase->exec($this->sysPlayerId, 'no_such_item', 1);
    }

    private function insertTestData(): void
    {
        DB::connection('mst')->table('mst_player_level')->insert([
            ['level' => 1, 'required_exp' => 0, 'max_stamina' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 2, 'required_exp' => 100, 'max_stamina' => 55, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 3, 'required_exp' => 500, 'max_stamina' => 60, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            [
                'id' => self::PLAYER_EXP_ITEM_ID,
                'type' => 'consumable',
                'effect' => 'player_exp',
                'value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::STAMINA_ITEM_ID,
                'type' => 'consumable',
                'effect' => 'stamina_recover',
                'value' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::UNIT_EXP_ITEM_ID,
                'type' => 'UnitEnhancement',
                'effect' => 'unit_exp',
                'value' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-use-item',
            'my_id' => 'TEST0010',
            'name' => 'tester',
            'level' => 1,
            'level_exp' => 0,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_stamina')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'type' => StaminaConst::TYPE_NORMAL,
            'current_stamina' => 10,
            'recovery_rate_multiplier' => 1.00,
            'last_recovery_at' => now()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([self::PLAYER_EXP_ITEM_ID, self::STAMINA_ITEM_ID, self::UNIT_EXP_ITEM_ID] as $itemId) {
            DB::connection('trx1')->table('trx_item')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => $itemId,
                'free_amount' => 10,
                'paid_amount' => 0,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        DB::connection('mst')->table('mst_item')->whereIn('id', [
            self::PLAYER_EXP_ITEM_ID,
            self::STAMINA_ITEM_ID,
            self::UNIT_EXP_ITEM_ID,
        ])->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('trx1')->table('trx_stamina')->delete();
        DB::connection('trx1')->table('trx_item')->delete();
    }
}
