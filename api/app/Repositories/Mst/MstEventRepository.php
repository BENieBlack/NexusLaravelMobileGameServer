<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstEvent;

/**
 * MstEventRepository
 *
 * @extends _BaseMstRepository<MstEvent>
 */
class MstEventRepository extends _BaseMstRepository
{
    protected string $modelClass = MstEvent::class;
}
