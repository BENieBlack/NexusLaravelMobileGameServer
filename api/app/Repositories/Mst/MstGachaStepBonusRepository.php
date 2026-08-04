<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonus;

/**
 * MstGachaStepBonusRepository
 * 
 * @extends _BaseMstRepository<MstGachaStepBonus>
 */
class MstGachaStepBonusRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaStepBonus::class;
}
