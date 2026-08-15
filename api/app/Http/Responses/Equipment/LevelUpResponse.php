<?php

namespace App\Http\Responses\Equipment;

use App\Http\Responses\_BaseResponse;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;

/**
 * LevelUpResponse
 *
 * 装備レベルアップAPIのレスポンス
 * trx_equipmentとtrx_itemの構造体を返し、クライアント側で判断する
 */
class LevelUpResponse extends _BaseResponse
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {}

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
        ];
    }
}
