<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstTrade;

/**
 * MstTradeRepository
 *
 * @extends _BaseMstRepository<MstTrade>
 */
class MstTradeRepository extends _BaseMstRepository
{
    protected string $modelClass = MstTrade::class;
}
