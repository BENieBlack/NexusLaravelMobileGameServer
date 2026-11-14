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
        // 表示名（mst_item__l10n）も消す。残すと他のテストの後始末と食い違う
        DB::connection('mst')->table('mst_item__l10n')->delete();
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
    public function 通貨系とスタミナ回復薬は図鑑の対象外で他の行は既定のまま(): void
    {
        // item 型の配布は図鑑の unlock 対象。載せない 13 行は is_album_target=false にする（人の決定）
        $excluded = ['free_diamond', 'gold', 'coin', 'food', 'wood', 'stone', 'iron', 'alliance_points',
            'pvp_points', 'event_points', 'achievement_points', 'vip_points', 'stamina_50'];

        $flags = DB::connection('mst')->table('mst_item')->pluck('is_album_target', 'id')->map(fn ($v) => (int) $v);

        foreach ($excluded as $id) {
            $this->assertSame(0, $flags[$id], "{$id} は図鑑の対象外");
        }

        // 回復・ブースト・経験値・unit_exp・素材など残りの 16 行は列の既定（true）のまま
        $others = $flags->except($excluded);
        $this->assertCount(16, $others);
        $this->assertSame(16, $others->filter(fn ($v) => $v === 1)->count());
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
    public function スタミナ回復薬50はスタミナ回復の行で入る(): void
    {
        // ログインボーナス 5 日目の景品。/item/use で value × 個数だけスタミナが回復する
        $row = DB::connection('mst')->table('mst_item')->where('id', 'stamina_50')->first();

        $this->assertNotNull($row);
        $this->assertSame('stamina', $row->type);
        $this->assertSame('stamina_recover', $row->effect);
        $this->assertSame(50, (int) $row->value);
        $this->assertSame(0, (int) $row->is_wallet, 'trx_item で持つので Wallet 管理ではない');
    }

    #[Test]
    public function mst_itemは29行(): void
    {
        $this->assertSame(29, DB::connection('mst')->table('mst_item')->count());
    }

    #[Test]
    public function 全てのアイテムにjaの表示名がある(): void
    {
        // クライアントはホーム・ログインボーナスで全アイテムの名前を mst_item__l10n から引く。
        // 行が無いと名前が出ないので、mst_item の全行に ja の行があることを固定する
        $itemIds = DB::connection('mst')->table('mst_item')->pluck('id');
        $this->assertNotEmpty($itemIds);

        $l10n = DB::connection('mst')->table('mst_item__l10n')->where('language', 'ja')->get()->keyBy('mst_item_id');

        foreach ($itemIds as $id) {
            $this->assertTrue($l10n->has($id), "{$id} の ja の表示名が無い");
            $this->assertNotSame('', trim((string) $l10n[$id]->name), "{$id} の名前が空");
            $this->assertNull($l10n[$id]->description, "{$id} の description は null");
        }

        // 表示名の行は mst_item の行と 1 対 1（余分な行が無い。ja 以外の言語は入れない）
        $this->assertSame($itemIds->count(), DB::connection('mst')->table('mst_item__l10n')->count());
    }

    #[Test]
    public function l10nのdeploy_keyはmst_itemと同じ(): void
    {
        $itemKeys = DB::connection('mst')->table('mst_item')->pluck('deploy_key')->unique()->values()->all();
        $l10nKeys = DB::connection('mst')->table('mst_item__l10n')->pluck('deploy_key')->unique()->values()->all();

        $this->assertSame($itemKeys, $l10nKeys);
    }

    #[Test]
    public function 無償ダイヤと主要アイテムの表示名(): void
    {
        $name = fn (string $id) => DB::connection('mst')->table('mst_item__l10n')
            ->where('mst_item_id', $id)->where('language', 'ja')->value('name');

        $this->assertSame('無償ダイヤ', $name(InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID));
        $this->assertSame('スタミナ回復薬（50）', $name('stamina_50'));
        $this->assertSame('ゴールド', $name('gold'));
    }

    #[Test]
    public function 二回流しても行が増えない(): void
    {
        $count = DB::connection('mst')->table('mst_item')->count();
        $l10nCount = DB::connection('mst')->table('mst_item__l10n')->count();

        // 主キー重複（mst_item_id, language）で落ちない = 冪等（l10n も truncate して作り直す）
        (new MstItemSeeder)->run();

        $this->assertSame($count, DB::connection('mst')->table('mst_item')->count());
        $this->assertSame($l10nCount, DB::connection('mst')->table('mst_item__l10n')->count());
    }
}
