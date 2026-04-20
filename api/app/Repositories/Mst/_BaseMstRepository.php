<?php

namespace App\Repositories\Mst;

use App\Models\Mst\_BaseMst;
use App\Models\Mst\_BaseMstInterface;
use App\Repositories\_BaseRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * _BaseMstRepository
 *
 * マスターデータのRepository基底クラス
 * キャッシュ機能を含む読み取り専用操作を提供
 * 
 * @template T of _BaseMstInterface
 * @implements _BaseMstRepositoryInterface<T>
 */
abstract class _BaseMstRepository extends _BaseRepository implements _BaseMstRepositoryInterface
{
    /**
     * キャッシュキーのプレフィックス
     */
    protected string $cachePrefix = 'mst';

    /**
     * キャッシュドライバー名（Redis キャッシュを使用）
     */
    protected string $cacheDriver = 'redis';

    /**
     * データベース接続名
     */
    protected string $connection = 'mst';

    /**
     * データベースまたはメモリからデータを取得
     * キャッシュ（Redis）から取得、存在しない場合はDBから取得してキャッシュに保存
     * 
     * @return Collection<int|string, T>
     */
    public function queryOrMemory(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $modelInstance = new $this->modelClass;
        $tableName = $modelInstance->getTable();
        $cacheKey = "{$this->cachePrefix}:{$tableName}:all";

        // Laravel Cacheを使ってキャッシュから取得、なければDBから取得してキャッシュに保存
        $this->models = Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () {
                // 全レコードを取得し、IDをキーにしたコレクションを返す
                $all = $this->modelClass::all();
                return $all->keyBy('id');
            }
        );

        return $this->models;
    }

    /**
     * IDでマスターレコードを取得
     * メモリキャッシュから取得
     * 
     * @param int|string $mstRecordId
     * @return T|null
     */
    public function selectById($mstRecordId)
    {
        // 全データをメモリキャッシュにロード
        $this->queryOrMemory();
        
        // メモリキャッシュから取得
        return $this->getModel($mstRecordId);
    }

    /**
     * 複数のIDでマスターレコードを取得
     * メモリキャッシュから取得
     * 
     * @param array<int|string> $ids
     * @return Collection<int|string, T>
     */
    public function selectListByIds(array $ids): Collection
    {
        // 全データをメモリキャッシュにロード
        $this->queryOrMemory();
        
        // メモリキャッシュから複数取得
        return collect($ids)
            ->map(fn($id) => $this->getModel($id))
            ->filter() // null を除外
            ->values(); // キーをリセット
    }

    /**
     * setModel は Mst では使用しない（読み取り専用）
     * 
     * @param mixed $model
     * @return void
     * @throws \BadMethodCallException
     */
    public function setModel($model): void
    {
        throw new \BadMethodCallException('setModel() is not supported in MstRepository. MstRepository is read-only.');
    }

    /**
     * setModels は Mst では使用しない（読み取り専用）
     * 
     * @param Collection<int|string, mixed> $models
     * @return void
     * @throws \BadMethodCallException
     */
    protected function setModels(Collection $models): void
    {
        throw new \BadMethodCallException('setModels() is not supported in MstRepository. MstRepository is read-only.');
    }

    /**
     * キャッシュをクリアする（テスト用）
     * Redisキャッシュとメモリキャッシュの両方をクリアする
     * 
     * @return void
     */
    public function clearCache(): void
    {
        // メモリキャッシュをクリア
        $this->models = null;

        // Redisキャッシュをクリア
        $modelInstance = new $this->modelClass;
        $tableName = $modelInstance->getTable();
        $cacheKey = "{$this->cachePrefix}:{$tableName}:all";
        
        Cache::store($this->cacheDriver)->forget($cacheKey);
    }

    /**
     * 全てのMstリポジトリのキャッシュをクリアする（テスト用静的メソッド）
     * 
     * @return void
     */
    public static function clearAllCaches(): void
    {
        // Redisキャッシュ全体をクリア（テスト環境のみで使用）
        /** @var \Illuminate\Cache\RedisStore $store */
        $store = Cache::store('redis');
        $store->flush();
    }
}
