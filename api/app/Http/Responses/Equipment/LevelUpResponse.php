<?php

namespace App\Http\Responses\Equipment;

use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * LevelUpResponse
 * 
 * 装備レベルアップAPIのレスポンス
 * trx_equipmentとtrx_itemの構造体を返し、クライアント側で判断する
 */
class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {
    }

    /**
     * レスポンスを生成
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
        ]);
    }
}
