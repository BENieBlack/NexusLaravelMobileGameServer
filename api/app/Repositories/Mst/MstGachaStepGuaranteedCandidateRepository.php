<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepGuaranteedCandidate;
use Illuminate\Support\Collection;

/**
 * MstGachaStepGuaranteedCandidateRepository
 * 
 * @extends _BaseMstRepository<MstGachaStepGuaranteedCandidate>
 */
class MstGachaStepGuaranteedCandidateRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaStepGuaranteedCandidate::class;

    /**
     * 確定景品IDで候補リストを取得
     *
     * @param string $mstGachaStepGuaranteedId
     * @return Collection<int, MstGachaStepGuaranteedCandidate>
     */
    public function selectListByGuaranteedId(string $mstGachaStepGuaranteedId): Collection
    {
        $this->queryOrMemory();
        
        return $this->models
            ->where('mst_gacha_step_guaranteed_id', $mstGachaStepGuaranteedId)
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();
    }
}
