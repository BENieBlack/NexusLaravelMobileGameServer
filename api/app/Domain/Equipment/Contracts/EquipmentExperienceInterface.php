<?php

namespace App\Domain\Equipment\Contracts;

use App\Models\Trx\TrxEquipment;

/**
 * EquipmentExperienceInterface
 *
 * Equipmentモジュールの公開API。装備に経験値を加算する。
 */
interface EquipmentExperienceInterface
{
    /**
     * 装備に経験値を加算する
     *
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @return TrxEquipment 更新後の装備データ
     *
     * @throws \Exception 装備が存在しない場合
     */
    public function addExpAndReturn(int $trxEquipmentId, int $exp): TrxEquipment;
}
