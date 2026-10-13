<?php

namespace App\Domain\Equipment\DataTransferObjects;

use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;

/**
 * LevelUpResult
 *
 * 装備レベルアップの結果
 */
class LevelUpResult
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {}
}
