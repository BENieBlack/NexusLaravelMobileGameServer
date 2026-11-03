<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstTradeProduct;

/**
 * MstTradeProductRepository
 *
 * @extends _BaseMstRepository<MstTradeProduct>
 */
class MstTradeProductRepository extends _BaseMstRepository
{
    protected string $modelClass = MstTradeProduct::class;
}
