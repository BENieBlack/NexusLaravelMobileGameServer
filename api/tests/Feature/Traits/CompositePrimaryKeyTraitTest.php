<?php

namespace Tests\Feature\Traits;

use App\Models\Trx\TrxItem;
use App\Persistence\ApiSession;
use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 単一主キー用のスタブ
 *
 * トレイトは主キーが配列でないときに親へ委譲する分岐を持つ。
 * その分岐を通すために、既存のテーブルへ単一主キーで結びつける。
 */
class SingleKeyStub extends Model
{
    use CompositePrimaryKeyTrait;

    public $incrementing = false;

    public $timestamps = false;

    protected $connection = 'mst';

    protected $table = 'mst_item';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $guarded = [];
}

/**
 * CompositePrimaryKeyTrait のテスト
 *
 * Laravelは単一主キーを前提にUPDATEのWHERE句を組み立てるため、
 * 複合主キーのモデルはこのトレイトが無いと更新先を特定できない。
 * 9モデルが使っているので、崩れると広範囲に効く。
 *
 * 「同じプレイヤーの別アイテム」を巻き込まないことが要点。
 * 片方のキーしかWHEREに入らないと、そこが壊れる。
 */
class CompositePrimaryKeyTraitTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->connection = $this->playerConnection($this->sysPlayerId);
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 複合主キーの両方でwhereを組み立てる(): void
    {
        $model = new TrxItem;
        $model->setConnection($this->connection);
        $model->forceFill([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'item_a',
        ]);
        $model->syncOriginal();

        $query = $model->newQueryWithoutScopes();
        $model->setKeysForSaveQuery($query);

        $sql = $query->toSql();
        $this->assertStringContainsString('sys_player_id', $sql);
        $this->assertStringContainsString('mst_item_id', $sql);
        $this->assertSame([$this->sysPlayerId, 'item_a'], $query->getBindings());
    }

    #[Test]
    public function 更新が同じプレイヤーの別アイテムを巻き込まない(): void
    {
        $this->makeItem('item_a', freeAmount: 10);
        $this->makeItem('item_b', freeAmount: 20);

        _BaseModel::allowDirectWrites(function () {
            $item = TrxItem::on($this->connection)
                ->where('sys_player_id', $this->sysPlayerId)
                ->where('mst_item_id', 'item_a')
                ->first();

            $item->setFreeAmount(99);
            $item->save();
        });

        $this->assertSame(99, $this->freeAmount('item_a'));
        $this->assertSame(20, $this->freeAmount('item_b'), '別アイテムまで書き換わっている');
    }

    #[Test]
    public function 更新が別プレイヤーの同じアイテムを巻き込まない(): void
    {
        ['player' => $other] = $this->signUpPlayer();
        $otherPlayerId = $other->id;
        $otherConnection = $this->playerConnection($otherPlayerId);

        $this->makeItem('item_a', freeAmount: 10);
        DB::connection($otherConnection)->table('trx_item')->insert([
            'sys_player_id' => $otherPlayerId,
            'mst_item_id' => 'item_a',
            'free_amount' => 20,
            'paid_amount' => 0,
            'is_delete' => false,
        ]);

        _BaseModel::allowDirectWrites(function () {
            $item = TrxItem::on($this->connection)
                ->where('sys_player_id', $this->sysPlayerId)
                ->where('mst_item_id', 'item_a')
                ->first();

            $item->setFreeAmount(99);
            $item->save();
        });

        $this->assertSame(99, $this->freeAmount('item_a'));
        $this->assertSame(20, (int) DB::connection($otherConnection)->table('trx_item')
            ->where('sys_player_id', $otherPlayerId)
            ->where('mst_item_id', 'item_a')
            ->value('free_amount'), '別プレイヤーの行まで書き換わっている');

        DB::connection($otherConnection)->table('trx_item')
            ->where('sys_player_id', $otherPlayerId)->delete();
    }

    #[Test]
    public function whereには変更前のキーを使う(): void
    {
        // キー列を書き換えて保存したとき、新しい値でWHEREを組むと
        // どの行にも当たらず更新が黙って消える
        $model = new TrxItem;
        $model->setConnection($this->connection);
        $model->forceFill([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'item_before',
        ]);
        $model->syncOriginal();
        $model->setMstItemId('item_after');

        $query = $model->newQueryWithoutScopes();
        $model->setKeysForSaveQuery($query);

        $this->assertSame([$this->sysPlayerId, 'item_before'], $query->getBindings());
    }

    #[Test]
    public function 主キーが配列でなければ単一キーで組み立てる(): void
    {
        $model = new SingleKeyStub;
        $model->forceFill(['id' => 'item_single']);
        $model->syncOriginal();

        $query = $model->newQueryWithoutScopes();
        $model->setKeysForSaveQuery($query);

        $this->assertSame(['item_single'], $query->getBindings());
    }

    private function makeItem(string $mstItemId, int $freeAmount): void
    {
        DB::connection($this->connection)->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => $mstItemId,
            'free_amount' => $freeAmount,
            'paid_amount' => 0,
            'is_delete' => false,
        ]);
    }

    private function freeAmount(string $mstItemId): int
    {
        return (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->value('free_amount');
    }

    private function cleanUp(): void
    {
        DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->delete();
    }
}
