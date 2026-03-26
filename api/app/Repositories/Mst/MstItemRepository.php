<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstItem;

class MstItemRepository extends _BaseMstRepository
{
    protected string $modelClass = MstItem::class;
}
