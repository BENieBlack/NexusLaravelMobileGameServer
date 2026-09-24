<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysMaintenance;
use App\Models\Trx\TrxUnit;
use Carbon\CarbonImmutable;
use Nexus\Core\Exceptions\DirectWriteNotAllowedException;
use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * _BaseModel の状態判定と変換のテスト
 *
 * 全モデルの土台。UnitOfWorkはここの isNew() / needsUpdate() を見て
 * INSERTとUPDATEを振り分けるため、判定を誤ると書き込みが落ちるか
 * 二重に走る。
 *
 * 日時はDB取得時からレスポンスまで一貫してstringで扱う方針で、
 * 変換が要るところだけメソッドを通す。
 */
class BaseModelStateTest extends TestCase
{
    // ========================================
    // INSERT / UPDATE の判定
    // ========================================

    #[Test]
    public function 未保存のモデルは新規扱い(): void
    {
        $model = new TrxUnit(['sys_player_id' => 1, 'mst_unit_id' => 'unit_001']);

        $this->assertTrue($model->isNew());
        $this->assertFalse($model->needsUpdate(), '新規はUPDATE対象ではない');
        $this->assertTrue($model->needsSave());
    }

    #[Test]
    public function 保存済みで変更が無ければ書き込み不要(): void
    {
        $model = $this->existingUnit();

        $this->assertFalse($model->isNew());
        $this->assertFalse($model->needsUpdate());
        $this->assertFalse($model->needsSave(), '変更が無ければUPDATEを流さない');
    }

    #[Test]
    public function 保存済みで変更があればupdate対象(): void
    {
        $model = $this->existingUnit();
        $model->setAttribute('level', 2);

        $this->assertFalse($model->isNew());
        $this->assertTrue($model->needsUpdate());
        $this->assertTrue($model->needsSave());
    }

    #[Test]
    public function 同じ値を入れ直しても変更にはならない(): void
    {
        // 毎回UPDATEを流すと、変わっていない行まで書き込みが走る
        $model = $this->existingUnit();
        $model->setAttribute('level', 1);

        $this->assertFalse($model->needsUpdate());
    }

    // ========================================
    // 接続とテーブル
    // ========================================

    #[Test]
    public function テーブル名を取れる(): void
    {
        $this->assertSame('trx_unit', (new TrxUnit)->getTableName());
        $this->assertSame('sys_maintenance', (new SysMaintenance)->getTableName());
    }

    #[Test]
    public function unit_of_workを使うかどうかを持つ(): void
    {
        // trxはUnitOfWork経由、sysもキューイングする
        $this->assertTrue((new TrxUnit)->usesUnitOfWork());
    }

    #[Test]
    public function デバッグ用の配列に状態が出る(): void
    {
        $model = $this->existingUnit();
        $model->setAttribute('level', 5);

        $debug = $model->toDebugArray();

        $this->assertSame('trx_unit', $debug['table']);
        $this->assertTrue($debug['exists']);
        $this->assertSame(['level' => 5], $debug['dirty'], '変更中の差分が見える');
        $this->assertSame(1, $debug['original']['level']);
    }

    // ========================================
    // 日時の扱い
    // ========================================

    #[Test]
    public function 日時は既定では変換対象を持たない(): void
    {
        // getDates() を空にして、Eloquentの自動Carbonキャストを止めている
        $this->assertSame([], (new TrxUnit)->getDates());
    }

    #[Test]
    public function 日時ゲッターはcarbonが入っていても文字列にする(): void
    {
        $model = new SysMaintenance;
        $model->setAttribute('start_at', CarbonImmutable::parse('2026-03-15 12:00:00'));

        $this->assertSame('2026-03-15 12:00:00', $model->getStartAt());
    }

    #[Test]
    public function レスポンス用の配列は内部情報を落とす(): void
    {
        // sys_player_id や uuid をそのまま返すと他人の情報を推測できる
        $model = $this->existingUnit();

        $array = $model->toResponseArray();

        $this->assertArrayNotHasKey('sys_player_id', $array);
        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
        $this->assertSame('unit_001', $array['mst_unit_id']);
    }

    /**
     * 補足: toResponseArray() の catch (\Exception) は実質到達しない。
     * getDates() に載せた列は toArray() の時点でEloquentが日付として
     * 解釈するため、読めない値はそちらで先に落ちる。
     */
    #[Test]
    public function レスポンス用の配列は日時をiso8601にする(): void
    {
        $model = new DatedModel(['name' => 'test', 'start_at' => '2026-03-15 12:00:00']);

        $this->assertSame('2026-03-15T12:00:00+00:00', $model->toResponseArray()['start_at']);
    }

    #[Test]
    public function 日時にdatetimeが入っていてもiso8601にする(): void
    {
        $model = new DatedModel(['name' => 'test']);
        $model->setAttribute('start_at', new \DateTimeImmutable('2026-03-15 12:00:00'));

        $this->assertSame('2026-03-15T12:00:00+00:00', $model->toResponseArray()['start_at']);
    }

    // ========================================
    // 直接書き込みの禁止
    // ========================================

    #[Test]
    public function 強制削除もunit_of_workを迂回できない(): void
    {
        // テスト実行中は直接書き込みが許可されているため、明示的に戻す
        $wasAllowed = _BaseModel::directWritesAllowed();
        _BaseModel::disallowDirectWrites();

        try {
            $this->expectException(DirectWriteNotAllowedException::class);

            $this->existingUnit()->forceDelete();
        } finally {
            if ($wasAllowed) {
                _BaseModel::allowDirectWrites();
            }
        }
    }

    private function existingUnit(): TrxUnit
    {
        $model = new TrxUnit;
        $model->setRawAttributes([
            'id' => 1,
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_001',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
            'is_delete' => 0,
            'created_at' => '2026-03-15 12:00:00',
            'updated_at' => '2026-03-15 12:00:00',
        ], true);
        $model->exists = true;

        return $model;
    }
}

/**
 * getDates() に日時を持つモデル（レスポンス変換の確認用）
 */
class DatedModel extends _BaseModel
{
    protected $table = 'dated';

    /** @var list<string> */
    protected $fillable = ['name', 'start_at'];

    public function getDates()
    {
        return ['start_at'];
    }
}
