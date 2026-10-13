<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonus;
use NexusGacha\DataTransferObjects\StepBonus;
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
     */
    public function selectByStepId(string $stepId): array
    {
        $this->queryOrMemory();

        return $this->models
            ->where('mst_gacha_step_id', $stepId)
            ->where('is_active', true)
            ->sortBy('position')
            ->map(fn (MstGachaStepBonus $bonus) => new StepBonus(
                id: $bonus->id,
                position: $bonus->position,
                bonusCount: $bonus->getBonusCount(),
                selectionType: $bonus->getSelectionType(),
                bonusRarity: $bonus->getBonusRarity(),
                isPickupOnly: $bonus->isPickupOnly(),
            ))
            ->values()
            ->all();
    }
}
