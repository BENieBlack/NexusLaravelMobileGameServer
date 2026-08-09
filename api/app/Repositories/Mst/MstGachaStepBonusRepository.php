<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonus;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusPersistence\Support\CustomCollection;

/**
 * MstGachaStepBonusRepository
 *
 * @extends _BaseMstRepository<MstGachaStepBonus>
 */
class MstGachaStepBonusRepository extends _BaseMstRepository implements GachaStepBonusRepositoryInterface
{
    protected string $modelClass = MstGachaStepBonus::class;

    /**
     * {@inheritDoc}
     */
    public function findByStepId(string $stepId): CustomCollection
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_step_id', $stepId)
            ->where('is_active', true)
            ->sortBy('position')
            ->values();
    }
}
