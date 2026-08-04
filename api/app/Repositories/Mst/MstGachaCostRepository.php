<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaCost;

/**
 * MstGachaCostRepository
 * 
 * @extends _BaseMstRepository<MstGachaCost>
 */
class MstGachaCostRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaCost::class;

    /**
     * ガチャIDと実行回数でコスト情報を取得
     *
     * @param string $mstGachaId
     * @param int $drawCount
     * @return MstGachaCost|null
     */
    public function selectByGachaIdAndDrawCount(string $mstGachaId, int $drawCount): ?MstGachaCost
    {
        $this->queryOrMemory();
        
        return $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('draw_count', $drawCount)
            ->where('is_active', true)
            ->first();
    }
}
