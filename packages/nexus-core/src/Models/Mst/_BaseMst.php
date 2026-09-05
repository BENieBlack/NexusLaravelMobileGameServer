<?php

namespace Nexus\Core\Models\Mst;

use Nexus\Core\Exceptions\MasterDataReadOnlyException;
use Nexus\Core\Models\_BaseModel;

/**
 * _BaseMst
 *
 * Mstデータベースのモデル基底クラス
 * マスターデータ（読み取り専用）
 *
 * マスターデータはデプロイで投入するものであり、実行時に書き換えてはならない。
 * Eloquentモデルである以上 save() は呼べてしまうため、
 * 明示的に許可された文脈以外では例外を投げて落とす。
 *
 * 投入側（シーダー・デプロイ処理）は allowWrites() で許可する。
 *
 *     _BaseMst::allowWrites(function () {
 *         MstItem::create([...]);
 *     });
 */
abstract class _BaseMst extends _BaseModel implements _BaseMstInterface
{
    /**
     * 書き込みを許可するか
     *
     * マスターデータ投入時のみ true になる
     */
    protected static bool $writesAllowed = false;

    /**
     * マスターDB接続を使用
     *
     * @var string
     */
    protected $connection = 'mst';

    /**
     * Unit of Workパターンを使用しない（読み取り専用）
     */
    protected bool $usesUnitOfWork = false;

    /**
     * deploy_keyをfillableに追加
     * サブクラスで追加のfillableカラムを定義する場合は、
     * このカラムも含めてマージする必要があります
     *
     * @var array
     */
    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
    ];

    /**
     * マスターデータへの書き込みを許可する
     *
     * コールバックを渡した場合はその中だけ許可し、実行後に元へ戻す。
     * 渡さない場合は許可状態にする（テストのsetUp等で使う）。
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return TReturn|null
     */
    public static function allowWrites(?callable $callback = null): mixed
    {
        $previous = static::$writesAllowed;
        static::$writesAllowed = true;

        if ($callback === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            static::$writesAllowed = $previous;
        }
    }

    /**
     * マスターデータへの書き込みを再び禁止する
     */
    public static function disallowWrites(): void
    {
        static::$writesAllowed = false;
    }

    /**
     * 書き込みが許可されているか
     */
    public static function writesAllowed(): bool
    {
        return static::$writesAllowed;
    }

    /**
     * {@inheritDoc}
     *
     * @throws MasterDataReadOnlyException 許可されていない書き込みの場合
     */
    public function save(array $options = [])
    {
        $this->assertWritable('save');

        // マスターデータはUnitOfWorkの管理外であり、allowWrites()で
        // 明示的に許可された投入経路のみが到達する
        return self::allowDirectWrites(fn () => parent::save($options));
    }

    /**
     * {@inheritDoc}
     *
     * @throws MasterDataReadOnlyException 許可されていない書き込みの場合
     */
    public function update(array $attributes = [], array $options = [])
    {
        $this->assertWritable('update');

        return self::allowDirectWrites(fn () => parent::update($attributes, $options));
    }

    /**
     * {@inheritDoc}
     *
     * @throws MasterDataReadOnlyException 許可されていない書き込みの場合
     */
    public function delete()
    {
        $this->assertWritable('delete');

        return self::allowDirectWrites(fn () => parent::delete());
    }

    /**
     * {@inheritDoc}
     *
     * @throws MasterDataReadOnlyException 許可されていない書き込みの場合
     */
    public function forceDelete()
    {
        $this->assertWritable('forceDelete');

        return self::allowDirectWrites(fn () => parent::forceDelete());
    }

    /**
     * 書き込み可能か検証する
     *
     * @throws MasterDataReadOnlyException
     */
    protected function assertWritable(string $operation): void
    {
        if (static::$writesAllowed) {
            return;
        }

        throw MasterDataReadOnlyException::forOperation(static::class, $operation);
    }
}
