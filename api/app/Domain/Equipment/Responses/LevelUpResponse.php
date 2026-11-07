<?php

namespace App\Domain\Equipment\Responses;

use App\Domain\_BaseResponse;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;
use Nexus\Core\UnitOfWork\UpdatedResources;

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
        public readonly UpdatedResources $updatedResources,
    ) {}

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
            // この処理で更新された trx（trx_equipment_list、trx_item_list）。更新されたものだけが入る
            'updated_resources' => $this->updatedResources->toResponseArray(),
        ];
    }
}
