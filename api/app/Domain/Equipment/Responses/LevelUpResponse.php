<?php

namespace App\Domain\Equipment\Responses;

use App\Domain\_BaseResponse;
use App\Domain\ReturnsUpdatedResources;
use App\Traits\HasUpdatedResources;

/**
 * LevelUpResponse
 *
 * 装備レベルアップAPIのレスポンス
 * この処理で更新された trx（trx_equipment_list、trx_item_list）を返し、クライアント側で判断する
 */
class LevelUpResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use HasUpdatedResources;

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
