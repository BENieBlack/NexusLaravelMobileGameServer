<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstUnit;

/**
 * MstUnitRepository
 * 
 * @extends _BaseMstRepository<MstUnit>
 */
class MstUnitRepository extends _BaseMstRepository
{
    protected string $modelClass = MstUnit::class;
}
