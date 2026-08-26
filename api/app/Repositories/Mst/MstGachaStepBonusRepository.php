<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonus;
use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;

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
     *
     * @return CustomCollection<array-key, Model> インターフェースに合わせてModelで受ける
     */
    public function selectByStepId(string $stepId): CustomCollection
    {
        $this->queryOrMemory();

        /** @var CustomCollection<array-key, Model> $bonuses インターフェースの型に合わせて広げる */
        $bonuses = $this->models
            ->where('mst_gacha_step_id', $stepId)
            ->where('is_active', true)
            ->sortBy('position')
            ->values();

        return $bonuses;
    }
}
