<?php

namespace App\Repositories\Sys;

use App\Repositories\_BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * _BaseSysRepository
 * 
 * Sysデータベースのリポジトリ基底クラス
 * キャッシュ機能を含む共通のCRUD操作を実装
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
     * @return Collection<int|string, Model>
     */
    public function queryOrMemory(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        // DBから全レコードを取得してメモリキャッシュに保存
        $this->models = $this->modelClass::all()->keyBy('id');

        return $this->models;
    }

    /**
     * IDでモデルを取得
     * メモリキャッシュから取得、なければDBから取得
     *
     * @param int $id
     * @return Model|null
     */
    public function selectById(int $id): ?Model
    {
        // メモリキャッシュから取得を試みる
        $model = $this->getModel($id);
        
        if ($model !== null) {
            return $model;
        }
        
        // DBから取得してメモリキャッシュに保存
        $model = $this->modelClass::find($id);
        
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
    protected function getCacheKey(string $key): string
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
        $cacheKey = $this->getCacheKey($key);
        return Cache::store($this->cacheDriver)->forget($cacheKey);
    }
}
