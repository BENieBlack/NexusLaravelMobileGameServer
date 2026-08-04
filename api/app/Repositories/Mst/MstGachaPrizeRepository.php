<?php

namespace App\Repositories\Mst;


use NexusPersistence\Support\CustomCollection;
use App\Models\Mst\MstGachaPrize;

/**
 * MstGachaPrizeRepository
 * 
 * @extends _BaseMstRepository<MstGachaPrize>
 */
class MstGachaPrizeRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaPrize::class;

    /**
     * ガチャIDとレアリティで景品リストを取得
     *
     * @param string $mstGachaId
     * @param int $rarity
     * @param bool $pickupOnly ピックアップのみ取得
     * @return CustomCollection<int, MstGachaPrize>
     */
    public function selectListByGachaIdAndRarity(
        string $mstGachaId,
        int $rarity,
        bool $pickupOnly = false
    ): CustomCollection {
        $this->queryOrMemory();
        
        $query = $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('rarity', $rarity)
            ->where('is_active', true);
        
        if ($pickupOnly) {
            $query = $query->where('is_pickup', true);
        }
        
        return $query->values();
    }
}
