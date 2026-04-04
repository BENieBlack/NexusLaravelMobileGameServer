<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaRarityRate;
use Illuminate\Support\Collection;

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
     * @return Collection<int, MstGachaRarityRate>
     */
    public function selectListByGachaId(string $mstGachaId): Collection
    {
        $this->queryOrMemory();
        
        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->sortBy('rarity')
            ->values();
    }
}
