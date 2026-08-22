<?php

namespace Nexus\Core\Repositories\Mst;

use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Models\Mst\_BaseMstInterface;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Support\CustomCollection;
use Illuminate\Support\Facades\Cache;

/**
 * _BaseMstRepository
 *
 * マスターデータのRepository基底クラス
 * キャッシュ機能を含む読み取り専用操作を提供
 * 
 * @template T of _BaseMst
 * @extends _BaseRepository<int|string, T>
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
     * @return CustomCollection<int|string, T>
     */
    public function queryOrMemory(): CustomCollection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $modelInstance = new $this->modelClass;
        $tableName = $modelInstance->getTable();
        $cacheKey = "{$this->cachePrefix}:{$tableName}:all";

        // Laravel Cacheを使ってキャッシュから取得、なければDBから取得してキャッシュに保存
        $cached = Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () {
                // 全レコードを取得し、IDをキーにしたコレクションを返す
                $all = $this->modelClass::all();
                return $all->keyBy('id')->all(); // 配列として保存
            }
        );

        // CustomCollectionとして保存
        /** @var CustomCollection<int|string, T> $models */
        $models = new CustomCollection($cached);
        $this->models = $models;

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
        return $this->findCachedModel($mstRecordId);
    }

    /**
     * 複数のIDでマスターレコードを取得
     * メモリキャッシュから取得
     * 
     * @param array<int|string> $ids
     * @return CustomCollection<int, T> values()でキーを詰め直すため0始まりのint
     */
    public function selectListByIds(array $ids): CustomCollection
    {
        // 全データをメモリキャッシュにロード
        $this->queryOrMemory();
        
        // メモリキャッシュから複数取得
        return (new CustomCollection($ids))
            ->map(fn($id) => $this->findCachedModel($id))
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
     * @param CustomCollection<int|string, mixed> $models
     * @return void
     * @throws \BadMethodCallException
     */
    protected function setModels(CustomCollection $models): void
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
