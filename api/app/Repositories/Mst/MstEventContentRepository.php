<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstEventContent;

/**
 * MstEventContentRepository
 *
 * @extends _BaseMstRepository<MstEventContent>
 */
class MstEventContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstEventContent::class;
}
