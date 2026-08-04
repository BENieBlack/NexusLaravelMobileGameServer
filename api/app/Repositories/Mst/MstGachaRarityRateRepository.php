<?php

namespace App\Repositories\Mst;


use NexusPersistence\Support\CustomCollection;
use App\Models\Mst\MstGachaRarityRate;

/**
 * MstGachaRarityRateRepository
 * 
 * @extends _BaseMstRepository<MstGachaRarityRate>
 */
class MstGachaRarityRateRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaRarityRate::class;

    /**
     * ガチャIDでレアリティ排出率リストを取得
     *
     * @param string $mstGachaId
     * @return CustomCollection<int, MstGachaRarityRate>
     */
    public function selectListByGachaId(string $mstGachaId): CustomCollection
    {
        $this->queryOrMemory();
        
        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->sortBy('rarity')
            ->values();
    }
}
