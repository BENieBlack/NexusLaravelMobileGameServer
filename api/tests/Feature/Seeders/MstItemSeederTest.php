<?php

namespace Tests\Feature\Seeders;

use App\Domain\Player\Constants\InitialGrantConst;
use Database\Seeders\MstItemSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * アイテムマスターのシーダー（MstItemSeeder）のテスト。
 *
 * シーダーは truncate（暗黙コミット）するので、トランザクションで包まず、行は tearDown で消す。
 */
class MstItemSeederTest extends TestCase
{
    use RefreshMultipleDatabases;

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        (new MstItemSeeder)->run();
    }

    protected function tearDown(): void
    {
        DB::connection('mst')->table('mst_item')->delete();
        $this->refreshMstCache();

        parent::tearDown();
    }

    #[Test]
    public function wallet管理のアイテムはis_walletがtrueで入る(): void
    {
        // 以前は MstItem::create に is_wallet を渡しておらず、coin・gold も false になっていた
        foreach (['gold', 'coin', 'food', 'wood', 'stone', 'iron', 'alliance_points', 'pvp_points', 'event_points', 'achievement_points', 'vip_points'] as $id) {
            $row = DB::connection('mst')->table('mst_item')->where('id', $id)->first();

            $this->assertNotNull($row, "{$id} の行が無い");
            $this->assertSame(1, (int) $row->is_wallet, "{$id} は is_wallet = true");
        }
    }

    #[Test]
    public function free_diamondの行はis_walletがfalseで入る(): void
    {
        $row = DB::connection('mst')->table('mst_item')->where('id', InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID)->first();

        $this->assertNotNull($row);
        $this->assertSame('free_diamond', $row->id);
        $this->assertSame('currency', $row->type);
        $this->assertSame('none', $row->effect);
        $this->assertSame(0, (int) $row->value);
        $this->assertSame(0, (int) $row->is_wallet);
    }

    #[Test]
    public function wallet管理でないアイテムはis_walletがfalse(): void
    {
        $this->assertSame(0, (int) DB::connection('mst')->table('mst_item')->where('id', 'item_heal_001')->value('is_wallet'));
        $this->assertSame(
            0,
            DB::connection('mst')->table('mst_item')->where('is_wallet', false)->whereIn('id', ['gold', 'coin'])->count()
        );
    }

    #[Test]
    public function idは文字列で自動採番ではない(): void
    {
        $ids = DB::connection('mst')->table('mst_item')->pluck('id');

        $this->assertSame($ids->unique()->count(), $ids->count());
        $this->assertTrue($ids->contains('free_diamond'));
        $this->assertFalse($ids->contains(fn ($id) => is_numeric($id)), 'id は人が決めた文字列');
    }

    #[Test]
    public function 二回流しても行が増えない(): void
    {
        $count = DB::connection('mst')->table('mst_item')->count();

        (new MstItemSeeder)->run();

        $this->assertSame($count, DB::connection('mst')->table('mst_item')->count());
    }
}
