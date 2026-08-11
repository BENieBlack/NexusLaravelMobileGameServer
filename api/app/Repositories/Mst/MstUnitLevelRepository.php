<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstUnitLevel;
use Illuminate\Support\Facades\Cache;
use Nexus\Core\Support\CustomCollection;

/**
 * MstUnitLevelRepository
 *
 * ユニットレベルマスターデータのRepository
 * キャッシュ機能を含む読み取り専用操作を提供
 *
 * 【重要】経験値の仕様について
 * ---------------------------------
 * required_expは「レベル1から見て、そのレベルに到達するまでに必要な累積経験値」を表します。
 * レベルアップしても経験値はリセットされません。
 *
 * 使用例:
 * ```php
 * // 現在level=2、累積経験値=5000のURユニット
 * $currentLevel = 2;
 * $currentExp = 5000;
 * $rarity = 'UR';
 *
 * // 次のレベルに必要な累積経験値を取得
 * $nextLevelData = $repository->selectByRarityAndLevel($rarity, $currentLevel + 1);
 *
 * if ($currentExp >= $nextLevelData->required_exp) {
 *     // レベルアップ可能
 * }
 * ```
 *
 * @extends _BaseMstRepository<MstUnitLevel>
 */
class MstUnitLevelRepository extends _BaseMstRepository
{
    protected string $modelClass = MstUnitLevel::class;

    /**
     * キャッシュからデータを取得、存在しない場合はDBから取得してキャッシュに保存
     * レアリティをキーにしてキャッシュ
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
                // 全レコードを取得（配列として保存）
                return $modelInstance::all()->all();
            }
        );

        // CustomCollectionとして保持する
        $this->models = new CustomCollection($cached);

        return $this->models;
    }

    /**
     * レアリティとレベルで検索
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $level  レベル
     */
    public function selectByRarityAndLevel(string $rarity, int $level): ?MstUnitLevel
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->where('rarity', $rarity)
            ->where('level', $level)
            ->first();
    }

    /**
     * 指定レアリティの全レベルデータを取得
     *
     * @param  string  $rarity  レアリティ
     * @return Collection
     */
    public function selectAllByRarity(string $rarity): CustomCollection
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->where('rarity', $rarity)
            ->sortBy('level')
            ->values();
    }

    /**
     * 全レベルデータを取得
     *
     * @return Collection
     */
    public function selectAll(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * 指定レアリティの最大レベルを取得
     *
     * @param  string  $rarity  レアリティ
     */
    public function getMaxLevel(string $rarity): ?int
    {
        $allRecords = $this->queryOrMemory();

        return $allRecords->where('rarity', $rarity)->max('level');
    }

    /**
     * 累積経験値から現在のレベルを計算
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $exp  累積経験値
     * @return int 現在のレベル
     */
    public function calculateLevelFromExp(string $rarity, int $exp): int
    {
        $allRecords = $this->queryOrMemory();

        // 指定レアリティのレコードのみ取得
        $levelCollection = $allRecords->where('rarity', $rarity)
            ->sortByDesc('level');

        // 降順でソートし、最初にrequired_exp <= expとなるレベルを見つける
        foreach ($levelCollection as $levelData) {
            if ($exp >= $levelData->required_exp) {
                return $levelData->level;
            }
        }

        // 最小レベル（1）を返す
        return 1;
    }

    /**
     * 指定レアリティ・レベルに必要な累積経験値を取得
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $level  レベル
     */
    public function getRequiredExpForLevel(string $rarity, int $level): ?int
    {
        $levelData = $this->selectByRarityAndLevel($rarity, $level);

        return $levelData?->required_exp;
    }

    /**
     * 次のレベルに必要な残り経験値を計算
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $currentLevel  現在のレベル
     * @param  int  $currentExp  現在の累積経験値
     * @return int|null 残り経験値（最大レベルの場合はnull）
     */
    public function getRemainingExpForNextLevel(string $rarity, int $currentLevel, int $currentExp): ?int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->selectByRarityAndLevel($rarity, $nextLevel);

        if (! $nextLevelData) {
            // 最大レベルに到達している
            return null;
        }

        $remaining = $nextLevelData->required_exp - $currentExp;

        return max(0, $remaining);
    }
}
