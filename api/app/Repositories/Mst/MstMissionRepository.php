<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstMission;

/**
 * MstMissionRepository
 *
 * @extends _BaseMstRepository<MstMission>
 */
class MstMissionRepository extends _BaseMstRepository
{
    protected string $modelClass = MstMission::class;
}
