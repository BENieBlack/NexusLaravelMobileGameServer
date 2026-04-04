<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaPrize;
use Illuminate\Support\Collection;

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
     * @return Collection<int, MstGachaPrize>
     */
    public function selectListByGachaIdAndRarity(
        string $mstGachaId,
        int $rarity,
        bool $pickupOnly = false
    ): Collection {
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
