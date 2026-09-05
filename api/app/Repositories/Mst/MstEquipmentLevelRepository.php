<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstEquipmentLevel;
use Nexus\Core\Support\CustomCollection;

/**
 * MstEquipmentLevelRepository
 *
 * 装備レベルマスターデータのRepository
 * キャッシュ機能を含む読み取り専用操作を提供
 *
 * 【重要】経験値の仕様について
 * ---------------------------------
 * required_expは「レベル1から見て、そのレベルに到達するまでに必要な累積経験値」を表します。
 * レベルアップしても経験値はリセットされません。
 *
 * @extends _BaseMstRepository<MstEquipmentLevel>
 */
class MstEquipmentLevelRepository extends _BaseMstRepository
{
    protected string $modelClass = MstEquipmentLevel::class;

    /**
     * レアリティとレベルで検索
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $level  レベル
     */
    public function selectByRarityAndLevel(string $rarity, int $level): ?MstEquipmentLevel
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
     * @return CustomCollection<array-key, MstEquipmentLevel>
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
     * @return CustomCollection<array-key, MstEquipmentLevel>
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
    public function selectMaxLevel(string $rarity): ?int
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
    public function findRequiredExpForLevel(string $rarity, int $level): ?int
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
    public function calcRemainingExpForNextLevel(string $rarity, int $currentLevel, int $currentExp): ?int
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
