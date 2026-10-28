<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstTagContent;

/**
 * MstTagContentRepository
 *
 * @extends _BaseMstRepository<MstTagContent>
 */
class MstTagContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstTagContent::class;
}
