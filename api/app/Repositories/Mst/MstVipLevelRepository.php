<?php

namespace App\Repositories\Mst;

use Nexus\Core\Support\CustomCollection;
use NexusVip\Models\MstVipLevel;
use NexusVip\Repositories\VipLevelRepositoryInterface;

/**
 * MstVipLevelRepository
 *
 * VIPレベルマスターのRepository
 *
 * @extends _BaseMstRepository<MstVipLevel>
 */
class MstVipLevelRepository extends _BaseMstRepository implements VipLevelRepositoryInterface
{
    protected string $modelClass = MstVipLevel::class;

    /**
     * 全VIPレベルを取得（キャッシュから）
     * required_point昇順でソート
     *
     * @return CustomCollection<MstVipLevel>
     */
    public function selectAllLevels(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->sortBy('required_point')
            ->values();
    }

    /**
     * VIPレベル番号で検索
     *
     * @param  int  $level  VIPレベル
     */
    public function selectByLevel(int $level): ?MstVipLevel
    {
        return $this->queryOrMemory()
            ->where('level', $level)
            ->where('is_active', true)
            ->first();
    }

    /**
     * VIPレベルIDで検索
     *
     * @param  string  $id  VIPレベルID (例: "vip_5")
     */
    public function selectById($id): ?MstVipLevel
    {
        return parent::selectById($id);
    }

    /**
     * 必要ポイント以下の最大レベルを取得
     * 累積VIPポイントから該当するVIPレベルを判定
     *
     * @param  int  $points  累積VIPポイント
     */
    public function selectMaxLevelByPoints(int $points): MstVipLevel
    {
        // required_point降順でソートし、pointsを超えない最初のレベルを返す
        $level = $this->queryOrMemory()
            ->where('is_active', true)
            ->sortByDesc('required_point')
            ->first(function (MstVipLevel $vipLevel) use ($points) {
                return $points >= $vipLevel->getRequiredPoint();
            });

        // 該当するレベルがない場合はVIP0を返す
        if ($level === null) {
            $level = $this->selectByLevel(0);

            // VIP0も見つからない場合は例外
            if ($level === null) {
                throw new \RuntimeException('VIP level 0 not found in master data');
            }
        }

        return $level;
    }

    /**
     * 有効な全VIPレベルをlevel昇順で取得
     *
     * @return CustomCollection<MstVipLevel>
     */
    public function selectAllActiveOrderByLevel(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->sortBy('level')
            ->values();
    }
}
