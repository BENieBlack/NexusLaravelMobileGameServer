<?php

namespace App\Http\Responses\Equipment;

use App\Domain\Equipment\DataTransferObjects\LevelUpResult;
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
     * レベルアップ結果からレスポンスを生成
     */
    public static function fromResult(LevelUpResult $result): self
    {
        return new self(
            trxEquipment: $result->trxEquipment,
            trxItem: $result->trxItem,
        );
    }

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
