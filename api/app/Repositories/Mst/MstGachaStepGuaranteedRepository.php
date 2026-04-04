<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaStepGuaranteed;

/**
 * MstGachaStepGuaranteedRepository
 * 
 * @extends _BaseMstRepository<MstGachaStepGuaranteed>
 */
class MstGachaStepGuaranteedRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGachaStepGuaranteed::class;
}
