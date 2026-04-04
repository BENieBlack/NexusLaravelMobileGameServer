<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGacha;

/**
 * MstGachaRepository
 * 
 * @extends _BaseMstRepository<MstGacha>
 */
class MstGachaRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGacha::class;
}
