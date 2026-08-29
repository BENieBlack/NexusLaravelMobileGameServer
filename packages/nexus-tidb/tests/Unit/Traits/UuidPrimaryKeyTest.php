<?php

namespace NexusTidb\Tests\Unit\Traits;

use Illuminate\Database\Eloquent\Model;
use NexusTidb\Traits\UuidPrimaryKey;
use NexusTidb\Support\TidbMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UuidPrimaryKey のユニットテスト
 *
 * TiDBは AUTO_INCREMENT の単調増加を保証しないため、
 * 単一主キー id をUUIDで払い出す。
 *
 * 効くのは「TiDB利用時」かつ「主キーが単一のid」のときだけで、
 * 複合主キーや id 以外の主キーには触らないことが要点。
 */
class UuidPrimaryKeyTest extends TestCase
{
    protected function tearDown(): void
    {
        TidbMode::resetForTest();

        parent::tearDown();
    }

    #[Test]
    public function tidbでなければ何も変えない(): void
    {
        TidbMode::fakeForTest(false);

        $model = new UuidKeyStub;

        $this->assertNull($model->getAttribute('id'), 'AUTO_INCREMENTに任せる');
        $this->assertTrue($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
    }

    #[Test]
    public function tidbなら生成時にuuidが入る(): void
    {
        TidbMode::fakeForTest(true);

        $model = new UuidKeyStub;

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $model->getAttribute('id'),
            'ランダム性のあるUUID v4であること（連番だと書き込みが偏る）'
        );
    }

    #[Test]
    public function tidbならauto_incrementではなくなる(): void
    {
        TidbMode::fakeForTest(true);

        $model = new UuidKeyStub;

        // BatchExecutorはこの値を見てLAST_INSERT_ID()での上書きを判断する
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
    }

    #[Test]
    public function 生成ごとに違うuuidになる(): void
    {
        TidbMode::fakeForTest(true);

        $this->assertNotSame(
            (new UuidKeyStub)->getAttribute('id'),
            (new UuidKeyStub)->getAttribute('id'),
        );
    }

    #[Test]
    public function すでにidが入っていれば上書きしない(): void
    {
        TidbMode::fakeForTest(true);

        $model = new UuidKeyStub(['id' => 'existing-id']);

        $this->assertSame('existing-id', $model->getAttribute('id'));
    }

    #[Test]
    public function dbから読んだ行のidは保たれる(): void
    {
        TidbMode::fakeForTest(true);

        $model = (new UuidKeyStub)->newFromBuilder(['id' => 'from-db', 'name' => 'x']);

        $this->assertSame('from-db', $model->getAttribute('id'));
        $this->assertTrue($model->exists);
    }

    #[Test]
    public function 複合主キーのモデルには触らない(): void
    {
        TidbMode::fakeForTest(true);

        $model = new CompositeKeyStub;

        // idカラムを持たないテーブルなので、余計な属性を差してはいけない
        $this->assertArrayNotHasKey('id', $model->getAttributes());
        $this->assertFalse($model->usesUuidPrimaryKey());
    }

    #[Test]
    public function 主キーがid以外のモデルには触らない(): void
    {
        TidbMode::fakeForTest(true);

        $model = new PlayerKeyStub;

        $this->assertArrayNotHasKey('id', $model->getAttributes());
        $this->assertNull($model->getAttribute('sys_player_id'));
        $this->assertFalse($model->usesUuidPrimaryKey());
    }

    #[Test]
    public function idのintキャストがあってもuuidは潰れない(): void
    {
        TidbMode::fakeForTest(true);

        $model = new IntCastKeyStub;

        $this->assertIsString($model->getAttribute('id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $model->getAttribute('id'));
    }
}

/**
 * 単一主キー id を持つモデル
 */
class UuidKeyStub extends Model
{
    use UuidPrimaryKey;

    protected $table = 'trx_stub';

    protected $guarded = [];
}

/**
 * 複合主キーのモデル（idカラムを持たない）
 */
class CompositeKeyStub extends Model
{
    use UuidPrimaryKey;

    protected $table = 'trx_composite_stub';

    /** @var array<int, string> */
    protected $primaryKey = ['sys_player_id', 'type'];

    public $incrementing = false;

    protected $guarded = [];
}

/**
 * 主キーが sys_player_id のモデル
 */
class PlayerKeyStub extends Model
{
    use UuidPrimaryKey;

    protected $table = 'trx_player_stub';

    protected $primaryKey = 'sys_player_id';

    public $incrementing = false;

    protected $guarded = [];
}

/**
 * id を integer にキャストしているモデル（ログ系に多い）
 */
class IntCastKeyStub extends Model
{
    use UuidPrimaryKey;

    protected $table = 'log_stub';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
    ];
}
