<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstItem;

/**
 * MstItemRepository
 * 
 * @extends _BaseMstRepository<MstItem>
 */
class MstItemRepository extends _BaseMstRepository
{
    protected string $modelClass = MstItem::class;
}
