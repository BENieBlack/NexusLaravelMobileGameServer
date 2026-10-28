<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstMissionContent;

/**
 * MstMissionContentRepository
 *
 * @extends _BaseMstRepository<MstMissionContent>
 */
class MstMissionContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstMissionContent::class;
}
