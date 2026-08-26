<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstPlayerLevel;
use Illuminate\Support\Facades\Cache;
use Nexus\Core\Support\CustomCollection;

/**
 * MstPlayerLevelRepository
 *
 * プレイヤーレベルマスターデータのRepository
 * キャッシュ機能を含む読み取り専用操作を提供
 *
 * 【重要】経験値の仕様について
 * ---------------------------------
 * required_expは「レベル1から見て、そのレベルに到達するまでに必要な累積経験値」を表します。
 * レベルアップしても経験値はリセットされません。
 *
 * 例:
 * - level=1, required_exp=0
 * - level=2, required_exp=50   (累積)
 * - level=3, required_exp=141  (累積)
 *
 * 使用例:
 * ```php
 * // 現在level=2、累積経験値=100のプレイヤー
 * $currentLevel = 2;
 * $currentExp = 100;
 *
 * // 次のレベルに必要な累積経験値を取得
 * $nextLevelData = $repository->selectByLevel($currentLevel + 1);
 *
 * if ($currentExp >= $nextLevelData->required_exp) {
 *     // レベルアップ可能
 * }
 * ```
 *
 * @extends _BaseMstRepository<MstPlayerLevel>
 */
class MstPlayerLevelRepository extends _BaseMstRepository
{
    protected string $modelClass = MstPlayerLevel::class;

    /**
     * キャッシュからデータを取得、存在しない場合はDBから取得してキャッシュに保存
     * レベルをキーにしてキャッシュ
     */
    public function queryOrMemory(): CustomCollection
    {
        if (isset($this->models)) {
            return $this->models;
        }

        $modelInstance = new $this->modelClass;
        $tableName = $modelInstance->getTable();
        $cacheKey = "{$this->cachePrefix}:{$tableName}:all";

        // Laravel Cacheを使ってキャッシュから取得、なければDBから取得してキャッシュに保存
        $cached = Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () use ($modelInstance) {
                // 全レコードを取得し、levelをキーにした配列として保存
                return $modelInstance::all()->keyBy('level')->all();
            }
        );

        // CustomCollectionとして保持する
        $this->models = new CustomCollection($cached);

        return $this->models;
    }

    /**
     * レベルで検索
     *
     * @param  int  $level  レベル
     */
    public function selectByLevel(int $level): ?MstPlayerLevel
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->get($level);
    }

    /**
     * 全レベルデータを取得（レベル昇順）
     *
     * @return CustomCollection<array-key, MstPlayerLevel>
     */
    public function selectAll(): CustomCollection
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->sortBy('level')->values();
    }

    /**
     * 指定したレベルの最大スタミナを取得
     *
     * @param  int  $level  レベル
     */
    public function findMaxStaminaForLevel(int $level): ?int
    {
        $levelData = $this->selectByLevel($level);

        return $levelData?->max_stamina;
    }

    /**
     * 累積経験値から現在のレベルを計算
     *
     * 二分探索的にキャッシュデータから検索
     *
     * @param  int  $exp  累積経験値
     * @return int 現在のレベル
     */
    public function calculateLevelFromExp(int $exp): int
    {
        $allRecords = $this->queryOrMemory();

        // 降順でソートし、最初にrequired_exp <= expとなるレベルを見つける
        $levelCollection = $allRecords->sortByDesc('level');

        foreach ($levelCollection as $levelData) {
            if ($exp >= $levelData->required_exp) {
                return $levelData->level;
            }
        }

        // 最小レベル（1）を返す
        return 1;
    }

    /**
     * 最大レベルを取得
     */
    public function selectMaxLevel(): int
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->max('level') ?? 100;
    }

    /**
     * 指定レベルに必要な累積経験値を取得
     *
     * @param  int  $level  レベル
     */
    public function findRequiredExpForLevel(int $level): ?int
    {
        $levelData = $this->selectByLevel($level);

        return $levelData?->required_exp;
    }
}
