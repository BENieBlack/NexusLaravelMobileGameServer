<?php

namespace Nexus\Core\Repositories\Sys;

use Nexus\Core\Models\Sys\_BaseSys;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * _BaseSysRepository
 * 
 * Sysデータベースのリポジトリ基底クラス
 * キャッシュ機能を含む共通のCRUD操作を実装
 * 
 * @template T of _BaseSys
 * @extends _BaseRepository<int|string, T>
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
     * 新規モデル用の一時IDカウンター
     * モデルのIDがnullの場合に一意なキーを生成するために使用
     *
     * @var int
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
     * @param mixed $model
     * @return void
     */
    public function setModel($model): void
    {
        // created_at / updated_at はテーブル定義の
        // DEFAULT CURRENT_TIMESTAMP / ON UPDATE CURRENT_TIMESTAMP に任せる

        // ユニークキーを生成
        // 新規モデル（IDがnull）の場合は一時的なキーを割り当てる
        $uniqueKey = $this->buildUniqueKey($model) ?? '_new_' . $this->newModelCounter++;

        // 初回のsetModel時に変更前の状態を保存
        if (!isset($this->originalStateArray[$uniqueKey])) {
            $this->originalStateArray[$uniqueKey] = $model->getAttributes();
        }

        // CacheRecordTraitのキャッシュに保存
        if ($this->models === null) {
            $this->models = new CustomCollection();
        }
        $this->models->put($uniqueKey, $model);

        // 内部キューに溜め込む（同じキーは上書き = 最終状態を保持）
        $this->modelQueue[$uniqueKey] = $model;
    }

    /**
     * モデルキューをクリアし、カウンターと変更前状態をリセット
     *
     * @return void
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
     * @param mixed $model
     * @return string|null
     */
    protected function buildUniqueKey($model): ?string
    {
        $values = array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys());

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
     *
     * @return void
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
     * @param mixed $model 保存されたモデル（最終状態）
     * @param array<string, mixed> $originalState 変更前の状態（初回setModel時の状態）
     * @return void
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
     *
     * @return Model
     */
    protected function getModelInstance(): Model
    {
        return new $this->modelClass;
    }

    /**
     * データベースまたはメモリからデータを取得
     * メモリキャッシュのみ使用（特定のRepositoryでRedisを使う場合はオーバーライド）
     * 
     * @return CustomCollection<int|string, T>
     */
    public function queryOrMemory(): CustomCollection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        // DBから全レコードを取得してメモリキャッシュに保存
        $records = $this->modelClass::all()->keyBy('id');
        /** @var CustomCollection<int|string, T> $models */
        $models = new CustomCollection($records->all());
        $this->models = $models;

        return $this->models;
    }

    /**
     * IDでモデルを取得
     * メモリキャッシュから取得、なければDBから取得
     *
     * @param int $sysRecordId
     * @return T|null
     */
    public function selectById(int $sysRecordId)
    {
        // メモリキャッシュから取得を試みる
        $model = $this->findCachedModel($sysRecordId);
        
        if ($model !== null) {
            return $model;
        }
        
        // DBから取得してメモリキャッシュに保存
        $model = $this->modelClass::find($sysRecordId);
        
        if ($model !== null) {
            $this->setModel($model);
        }
        
        return $model;
    }

    /**
     * Redis キャッシュキーを生成
     *
     * @param string $key
     * @return string
     */
    protected function buildCacheKey(string $key): string
    {
        $modelInstance = $this->getModelInstance();
        $tableName = $modelInstance->getTable();
        return "{$this->cachePrefix}:{$tableName}:{$key}";
    }

    /**
     * Redis キャッシュをクリア
     *
     * @param string $key
     * @return bool
     */
    protected function clearCache(string $key): bool
    {
        $cacheKey = $this->buildCacheKey($key);
        return Cache::store($this->cacheDriver)->forget($cacheKey);
    }
}
