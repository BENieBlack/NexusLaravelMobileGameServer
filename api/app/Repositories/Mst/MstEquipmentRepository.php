<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstEquipment;

/**
 * MstEquipmentRepository
 * 
 * @extends _BaseMstRepository<MstEquipment>
 */
class MstEquipmentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstEquipment::class;
}
