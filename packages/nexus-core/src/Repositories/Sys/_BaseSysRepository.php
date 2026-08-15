<?php

namespace Nexus\Core\Repositories\Sys;

use Nexus\Core\Models\Sys\_BaseSysInterface;
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
 * @template T of _BaseSysInterface
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
        // タイムスタンプを自動設定
        // （UnitOfWork経由のINSERT/UPDATEはEloquentのタイムスタンプ処理を通らないため）
        $now = ClockUtility::now();
        $model->setAttribute('updated_at', $now);
        if ($model->getAttribute('created_at') === null) {
            $model->setAttribute('created_at', $now);
        }

        // ユニークキーを生成
        $uniqueKey = implode(':', array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys()));
        
        // 新規モデル（IDがnull）の場合、一時的なユニークキーを生成
        if ($uniqueKey === '' || $uniqueKey === ':' || strpos($uniqueKey, ':') === 0 || strpos($uniqueKey, ':') === strlen($uniqueKey) - 1) {
            $uniqueKey = '_new_' . $this->newModelCounter++;
        }

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
        parent::clearQueue();
        $this->originalStateArray = [];
        $this->newModelCounter = 0;
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
        $this->models = new CustomCollection($records->all());

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
