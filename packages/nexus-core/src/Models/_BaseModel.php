<?php

namespace Nexus\Core\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Exceptions\DirectWriteNotAllowedException;

/**
 * _BaseModel
 *
 * 全てのモデルの最上位基底クラス
 * 共通の振る舞いとヘルパーメソッドを提供
 */
abstract class _BaseModel extends Model implements _BaseModelInterface
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * データベース接続名
     *
     * シャードを跨ぐモデル（_BaseTrx / _BaseLog）は既定をnullにして
     * getConnectionName() で解決するため、nullを許容する
     *
     * @var string|null
     */
    protected $connection;

    /**
     * モデルがUnit of Workパターンで管理されるかどうか
     * Trx, Logモデルはtrue、Mst, Sysモデルはfalse
     */
    protected bool $usesUnitOfWork = false;

    /**
     * タイムスタンプフィールドの自動キャストを無効化
     * パフォーマンス最適化のため、DB取得時はstring型のまま保持し、
     * toResponseArray()で必要に応じてCarbonにキャストしてISO8601形式で返す
     *
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        // デフォルトのcreated_at, updated_atのdatetime自動キャストを無効化
        // サブクラスで必要に応じて個別にキャスト定義可能
    ];

    /**
     * Eloquentによる即時書き込みを許可するか
     *
     * 本番の実行時経路では常にfalse。
     * テストのフィクスチャやSeederのみ明示的に許可する。
     */
    protected static bool $directWritesAllowed = false;

    /**
     * @var list<string> 自身のデータ内で一意となるカラム名
     *
     * 未設定なら $primaryKey から決める。複合主キーは
     * $primaryKey を配列で書けばそのまま拾える。
     *
     * @example trx_item であれば ['sys_player_id', 'mst_item_id']
     */
    /** @var list<string> */
    protected array $uniqueKeys = [];

    /**
     * @var list<string> 自分に関係する行を絞り込むカラム名
     *
     * 複数指定した場合はORで繋ぐ。
     *
     * @example trx_unit であれば ['sys_player_id']
     * @example sys_friend_apply であれば ['sender_sys_player_id', 'receiver_sys_player_id']
     */
    /** @var list<string> */
    protected array $selectKeys = [];

    /**
     * Eloquentによる即時書き込みを許可する
     *
     * コールバックを渡した場合はその中だけ許可し、実行後に元へ戻す。
     * 渡さない場合は許可状態にする（テストのsetUp等で使う）。
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return TReturn|null
     */
    public static function allowDirectWrites(?callable $callback = null): mixed
    {
        $previous = self::$directWritesAllowed;
        self::$directWritesAllowed = true;

        if ($callback === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            self::$directWritesAllowed = $previous;
        }
    }

    /**
     * Eloquentによる即時書き込みを再び禁止する
     */
    public static function disallowDirectWrites(): void
    {
        self::$directWritesAllowed = false;
    }

    /**
     * 即時書き込みが許可されているか
     */
    public static function directWritesAllowed(): bool
    {
        return self::$directWritesAllowed;
    }

    /**
     * {@inheritDoc}
     *
     * UnitOfWorkを迂回した即時書き込みを禁止する。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     *
     * @throws DirectWriteNotAllowedException
     */
    public function save(array $options = [])
    {
        $this->assertDirectWriteAllowed('save');

        return parent::save($options);
    }

    /**
     * {@inheritDoc}
     *
     * UnitOfWorkを迂回した即時更新を禁止する。
     *
     * @throws DirectWriteNotAllowedException
     */
    public function update(array $attributes = [], array $options = [])
    {
        $this->assertDirectWriteAllowed('update');

        return parent::update($attributes, $options);
    }

    /**
     * {@inheritDoc}
     *
     * UnitOfWorkを迂回した即時削除を禁止する。
     * 削除はRepositoryのsoftDeleteModel() / hardDeleteModel()経由で行うこと。
     *
     * @throws DirectWriteNotAllowedException
     */
    public function delete()
    {
        $this->assertDirectWriteAllowed('delete');

        return parent::delete();
    }

    /**
     * {@inheritDoc}
     *
     * UnitOfWorkを迂回した即時削除を禁止する。
     *
     * @throws DirectWriteNotAllowedException
     */
    public function forceDelete()
    {
        $this->assertDirectWriteAllowed('forceDelete');

        return parent::forceDelete();
    }

    /**
     * 即時書き込みが許可されているか検証する
     *
     * @throws DirectWriteNotAllowedException
     */
    protected function assertDirectWriteAllowed(string $operation): void
    {
        if (self::$directWritesAllowed) {
            return;
        }

        throw DirectWriteNotAllowedException::forOperation(static::class, $operation);
    }

    /**
     * Eloquentのデフォルトタイムスタンプ自動キャストを無効化
     *
     * Eloquentは $timestamps = true のとき created_at / updated_at を
     * $casts の指定に関係なくCarbonへ変換する（HasAttributes::isDateAttribute()）。
     * 日時は文字列のまま扱う方針のため、変換対象を空にして無効化する。
     *
     * タイムスタンプの自動設定（$timestamps）自体は有効なまま。
     *
     * @return array<int, string|null>
     */
    public function getDates()
    {
        return [];
    }

    /**
     * 日付属性を Y-m-d H:i:s 形式の文字列として取得
     *
     * DBから取得した時点で文字列なので、そのまま返すのが基本。
     * Carbon等が入っている場合のみ整形する。
     *
     * 日時は文字列のまま扱う方針のため、比較は ClockUtility::isPast() /
     * isFuture() / isWithin() を使う（固定長なので辞書順=時系列順）。
     *
     * @param  string  $attribute  属性名（例: 'created_at', 'start_at'）
     */
    protected function getDateAttributeString(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * 日付属性をCarbonImmutable型として取得
     *
     * パフォーマンス最適化のため、DB取得時はstring型で保持し、
     * このメソッドで必要に応じてCarbonImmutable型に変換する
     *
     * @param  string  $attribute  属性名（例: 'created_at', 'start_at'）
     */
    protected function getDateAttribute(string $attribute): ?CarbonImmutable
    {
        $value = $this->getAttribute($attribute);

        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    /**
     * ユニークキーを取得
     *
     * 明示が無ければ主キーから決める。$primaryKey が配列なら
     * そのまま複合キーとして扱う。
     *
     * @return list<string>
     */
    public function getUniqueKeys(): array
    {
        if ($this->uniqueKeys !== []) {
            return $this->uniqueKeys;
        }

        /** @var string|array<int, string> $primaryKey */
        $primaryKey = $this->primaryKey;

        return array_values((array) $primaryKey);
    }

    /**
     * 絞り込みキーを取得
     *
     * @return list<string>
     */
    public function getSelectKeys(): array
    {
        return $this->selectKeys;
    }

    /**
     * Unit of Workパターンを使用するかどうか
     */
    public function usesUnitOfWork(): bool
    {
        return $this->usesUnitOfWork;
    }

    /**
     * データベース接続名を取得
     */
    public function getConnectionName(): string
    {
        return (string) $this->connection;
    }

    /**
     * テーブル名を取得（エイリアス）
     */
    public function getTableName(): string
    {
        return $this->getTable();
    }

    /**
     * モデルの属性を配列として取得（デバッグ用）
     */
    /**
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        return [
            'table' => $this->getTable(),
            'connection' => $this->getConnectionName(),
            'primaryKey' => $this->getKeyName(),
            'exists' => $this->exists,
            'attributes' => $this->attributes,
            'original' => $this->original,
            'changes' => $this->getChanges(),
            'dirty' => $this->getDirty(),
        ];
    }

    /**
     * モデルが新規作成かどうか（INSERTが必要か）
     */
    public function isNew(): bool
    {
        return ! $this->exists;
    }

    /**
     * モデルが更新対象かどうか（UPDATEが必要か）
     */
    public function needsUpdate(): bool
    {
        return $this->exists && $this->isDirty();
    }

    /**
     * モデルに変更があるかどうか（INSERT or UPDATEが必要か）
     */
    public function needsSave(): bool
    {
        return $this->isNew() || $this->needsUpdate();
    }

    /**
     * APIレスポンス用の配列に変換
     *
     * パフォーマンス最適化のため、DB取得時はstring型で保持し、
     * レスポンス生成時のみCarbonにパースしてISO8601形式に変換する
     *
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = $this->toArray();

        // 日付フィールドをISO8601形式に変換
        // DB取得時はstring型なので、ここで明示的にCarbonにパースする
        foreach ($this->getDates() as $dateField) {
            if (isset($array[$dateField]) && is_string($array[$dateField])) {
                try {
                    $carbon = Carbon::parse($array[$dateField]);
                    $array[$dateField] = $carbon->toIso8601String();
                } catch (\Exception $e) {
                    // パース失敗時は元の値をそのまま使用
                    // エラーログは出さずに続行（DBから取得した値は通常パース可能）
                }
            } elseif (isset($array[$dateField]) && $array[$dateField] instanceof \DateTimeInterface) {
                // 既にCarbon/DateTime型の場合（後方互換性のため）。
                // toIso8601String()はCarbon固有なので、素の\DateTimeでも通るformatを使う
                $array[$dateField] = $array[$dateField]->format(\DateTimeInterface::ATOM);
            }
        }

        // クライアントに渡さない内部情報を除外
        unset($array['sys_player_id']);
        unset($array['uuid']);
        unset($array['created_at']);
        unset($array['updated_at']);

        return $array;
    }
}
