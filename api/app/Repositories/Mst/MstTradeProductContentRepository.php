<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstTradeProductContent;

/**
 * MstTradeProductContentRepository
 *
 * @extends _BaseMstRepository<MstTradeProductContent>
 */
class MstTradeProductContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstTradeProductContent::class;
}
