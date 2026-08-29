<?php

namespace Nexus\Core\Repositories\Sys;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Nexus\Core\Models\Sys\_BaseSys;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Support\CustomCollection;

/**
 * _BaseSysRepository
 *
 * Sysデータベースのリポジトリ基底クラス
 * キャッシュ機能を含む共通のCRUD操作を実装
 *
 * @template T of _BaseSys
 *
 * @extends _BaseRepository<int|string, T>
 *
 * @implements _BaseSysRepositoryInterface<T>
 */
abstract class _BaseSysRepository extends _BaseRepository implements _BaseSysRepositoryInterface
{
    /**
     * キャッシュキーのプレフィックス
     */
    protected string $cachePrefix = 'sys';

    /**
     * キャッシュドライバー名（Redis を使用）
     */
    protected string $cacheDriver = 'redis';

    /**
     * データベース接続名
     */
    protected string $connection = 'sys';

    /**
     * 自分の行を絞り込む列
     *
     * queryOrMemory() はここに挙げた列でログイン中プレイヤーの行だけを読む。
     * 複数指定した場合はORで繋ぐ（フレンド申請のように、送信者でも受信者でも
     * 自分の行になるテーブル向け）。
     *
     * 空配列にすると自分スコープを持たないテーブルとして扱い、
     * queryOrMemory() は使えなくなる（selectWithoutCache() を使うこと）。
     *
     * @var list<string>
     */
    protected array $selfScopeKeys = ['sys_player_id'];

    /**
     * 現在のキャッシュがどのプレイヤーのものか
     *
     * リポジトリはリクエスト単位で共有されるため、
     * 途中でプレイヤーが切り替わったら読み直す
     */
    protected ?int $cachedForSysPlayerId = null;

    /**
     * 新規モデル用の一時IDカウンター
     * モデルのIDがnullの場合に一意なキーを生成するために使用
     */
    private int $newModelCounter = 0;

    /**
     * モデルの変更前状態を保持する配列
     * キー: ユニークキー, 値: オリジナル属性の配列
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $originalStateArray = [];

    /**
     * モデルをキャッシュに保存し、内部キューに溜め込む
     * ユニークキーで管理し、同じキーのモデルは上書きされる
     *
     * @param  mixed  $model
     */
    public function setModel($model): void
    {
        // created_at / updated_at はテーブル定義の
        // DEFAULT CURRENT_TIMESTAMP / ON UPDATE CURRENT_TIMESTAMP に任せる

        // ユニークキーを生成
        // 新規モデル（IDがnull）の場合は一時的なキーを割り当てる
        $uniqueKey = $this->buildUniqueKey($model) ?? '_new_'.$this->newModelCounter++;

        // 初回のsetModel時に変更前の状態を保存
        if (! isset($this->originalStateArray[$uniqueKey])) {
            $this->originalStateArray[$uniqueKey] = $model->getAttributes();
        }

        // CacheRecordTraitのキャッシュに保存
        if ($this->models === null) {
            $this->models = new CustomCollection;
        }
        $this->models->put($uniqueKey, $model);

        // 内部キューに溜め込む（同じキーは上書き = 最終状態を保持）
        $this->modelQueue[$uniqueKey] = $model;
    }

    /**
     * モデルキューをクリアし、カウンターと変更前状態をリセット
     */
    public function clearQueue(): void
    {
        // フラッシュでIDが採番されているので、仮キーを本来のキーへ振り直す
        $this->rekeyInsertedModels();

        parent::clearQueue();
        $this->originalStateArray = [];
        $this->newModelCounter = 0;
    }

    /**
     * ユニークキーを組み立てる
     *
     * 値が揃っていない（新規モデルでIDが未採番など）場合はnullを返す。
     *
     * @param  mixed  $model
     */
    protected function buildUniqueKey($model): ?string
    {
        $values = array_map(fn ($key) => $model->getAttribute($key), $this->getUniqueKeys());

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                return null;
            }
        }

        return $values === [] ? null : implode(':', $values);
    }

    /**
     * INSERT後のモデルをキャッシュ上で正しいキーに置き直す
     *
     * 新規モデルは採番前なので仮キー（_new_N）でキャッシュしている。
     * フラッシュでIDが入るため、そのままだと selectById() で引けない。
     */
    private function rekeyInsertedModels(): void
    {
        if ($this->models === null) {
            return;
        }

        foreach ($this->models as $key => $model) {
            if (! str_starts_with((string) $key, '_new_')) {
                continue;
            }

            $uniqueKey = $this->buildUniqueKey($model);

            if ($uniqueKey === null) {
                continue;
            }
            $this->models->forget($key);
            $this->models->put($uniqueKey, $model);
        }
    }

    /**
     * INSERT/UPDATE後のフック
     * サブクラスでオーバーライドして、ログ記録処理を実装
     *
     * @param  mixed  $model  保存されたモデル（最終状態）
     * @param  array<string, mixed>  $originalState  変更前の状態（初回setModel時の状態）
     */
    public function afterSave($model, array $originalState): void
    {
        // デフォルトでは何もしない
        // サブクラスでオーバーライドしてログ記録処理を実装
    }

    /**
     * 変更前の状態を取得
     *
     * @return array<string, array<string, mixed>> キー: ユニークキー, 値: オリジナル属性の配列
     */
    public function getOriginalStates(): array
    {
        return $this->originalStateArray;
    }

    /**
     * モデルインスタンスを取得
     */
    protected function getModelInstance(): Model
    {
        return new $this->modelClass;
    }

    /**
     * Sysテーブルの全件取得は禁止
     *
     * sys_player は全プレイヤー、sys_guild は全ギルドが1つのテーブルに入る。
     * Trxのようにプレイヤーで分かれていないため、全件を読むクエリは
     * 件数が増えた分だけそのまま重くなる。
     *
     * 器だけ残して落としているのは、うっかり生やしたときに
     * 黙って全件を読むのではなく、その場で気づけるようにするため。
     *
     * - 自分に関係する行  → queryOrMemory()
     * - それ以外を見たい  → selectWithoutCache() に条件と件数の上限を付ける
     *
     * @throws \LogicException 常に
     */
    final public function selectAll(): never
    {
        throw new \LogicException(sprintf(
            '%s: Sysテーブルの全件取得は禁止。'
            .'自分に関係する行は queryOrMemory()、それ以外は '
            .'selectWithoutCache() に条件と件数の上限を付けて使うこと',
            static::class
        ));
    }

    /**
     * ログイン中プレイヤーに関係する行だけを読み、メモリキャッシュに載せる
     *
     * Sysテーブルは全プレイヤー分が1つのテーブルに入っているため、
     * 全件読みは決してしない。$selfScopeKeys で自分の行に絞る。
     *
     * ここに載った行だけが setModel() で更新できる。
     * 他人の行や全体を見る用途には selectWithoutCache() を使う。
     *
     * @return CustomCollection<int|string, T>
     */
    public function queryOrMemory(): CustomCollection
    {
        if ($this->selfScopeKeys === []) {
            throw new \RuntimeException(sprintf(
                '%s は自分スコープを持たないため queryOrMemory() を使えない。selectWithoutCache() を使うこと',
                static::class
            ));
        }

        $sysPlayerId = $this->resolveSessionPlayerId();

        // 同じプレイヤーのキャッシュがあればそれを返す
        // 0件だった場合もキャッシュとして扱う（毎回問い合わせない）
        if ($this->models !== null && $this->cachedForSysPlayerId === $sysPlayerId) {
            return $this->models;
        }

        $query = $this->selectWithoutCache();
        $this->applySelfScope($query, $sysPlayerId);

        // ユニークキーのカラム値を連結してキーにする。
        // Collection::keyBy() は1件ずつクロージャを通すため、
        // リクエストごとに走るここでは素のforeachで組む
        $uniqueKeys = $this->getUniqueKeys();
        $keyed = [];

        foreach ($query->get() as $record) {
            $key = [];

            foreach ($uniqueKeys as $uniqueKey) {
                $key[] = $record->{$uniqueKey};
            }

            $keyed[implode(':', $key)] = $record;
        }

        /** @var CustomCollection<int|string, T> $cached */
        $cached = new CustomCollection($keyed);

        // 既に setModel で積んだ行はDBの値で上書きしない。
        // 上書きすると、フラッシュ前の変更が読み戻しで消える
        if ($this->models !== null) {
            foreach ($this->models as $key => $model) {
                $cached->put($key, $model);
            }
        }

        $this->models = $cached;
        $this->cachedForSysPlayerId = $sysPlayerId;

        return $this->models;
    }

    /**
     * 自分の行を絞り込む条件をクエリに足す
     *
     * $selfScopeKeys が複数ある場合はORで繋ぐ。
     *
     * @param  Builder<T>  $query
     */
    protected function applySelfScope(Builder $query, int $sysPlayerId): void
    {
        $selfScopeKeys = $this->selfScopeKeys;

        $query->where(function (Builder $builder) use ($selfScopeKeys, $sysPlayerId) {
            foreach ($selfScopeKeys as $column) {
                $builder->orWhere($column, $sysPlayerId);
            }
        });
    }

    /**
     * キャッシュにも更新キューにも載せない読み取り
     *
     * 他人のプロフィール、ギルド検索、認証前のトークン照合など、
     * 「自分の行ではないので更新しない」読み取りに使う。
     *
     * ここで得たモデルを setModel() に渡してはいけない。
     *
     * @return Builder<T>
     */
    protected function selectWithoutCache(): Builder
    {
        /** @var Builder<T> $query */
        $query = $this->modelClass::on($this->connection);

        return $query;
    }

    /**
     * 今このリクエストで自分スコープの読み取りが使えるか
     *
     * 認証前や、プレイヤーに紐づかないテーブルではfalseになる。
     */
    protected function hasSelfScope(): bool
    {
        return $this->selfScopeKeys !== [] && static::hasSysPlayerId();
    }

    /**
     * 渡されたIDがログイン中プレイヤー自身かどうか
     *
     * 認証前（サインアップ・トークン照合など）はfalseになる。
     */
    protected function isSessionPlayer(int $sysPlayerId): bool
    {
        return static::hasSysPlayerId() && static::getSysPlayerId() === $sysPlayerId;
    }

    /**
     * ログイン中プレイヤーのIDを取得
     *
     * @throws \RuntimeException 認証前など、プレイヤーが確定していない場合
     */
    protected function resolveSessionPlayerId(): int
    {
        if (static::hasSysPlayerId()) {
            return static::getSysPlayerId();
        }

        throw new \RuntimeException(
            'Player ID is not available. Make sure authentication middleware is applied.'
        );
    }

    /**
     * プレイヤーIDが設定されているかチェック
     * アプリケーション側の基底クラスでオーバーライドして実装する
     */
    protected static function hasSysPlayerId(): bool
    {
        throw new \RuntimeException('hasSysPlayerId() must be implemented by subclass or overridden by application');
    }

    /**
     * プレイヤーIDを取得
     * アプリケーション側の基底クラスでオーバーライドして実装する
     */
    protected static function getSysPlayerId(): int
    {
        throw new \RuntimeException('getSysPlayerId() must be implemented by subclass or overridden by application');
    }

    /**
     * IDでモデルを取得
     *
     * 自分に関係する行はキャッシュ経由で返す。
     * それ以外は読むだけで、返ったモデルを setModel() に渡してはいけない。
     *
     * @return T|null
     */
    public function selectById(int $sysRecordId)
    {
        // 自分に関係する行ならキャッシュから返す
        if ($this->hasSelfScope()) {
            $model = $this->queryOrMemory()->get((string) $sysRecordId);

            if ($model !== null) {
                return $model;
            }
        }

        // 自分スコープ外の行はキャッシュにも更新キューにも載せない
        return $this->selectWithoutCache()->find($sysRecordId);
    }

    /**
     * Redis キャッシュキーを生成
     */
    protected function buildCacheKey(string $key): string
    {
        $modelInstance = $this->getModelInstance();
        $tableName = $modelInstance->getTable();

        return "{$this->cachePrefix}:{$tableName}:{$key}";
    }

    /**
     * Redis キャッシュをクリア
     */
    protected function clearCache(string $key): bool
    {
        $cacheKey = $this->buildCacheKey($key);

        return Cache::store($this->cacheDriver)->forget($cacheKey);
    }
}
