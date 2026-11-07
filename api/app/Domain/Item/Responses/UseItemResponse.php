<?php

namespace App\Domain\Item\Responses;

use App\Domain\_BaseResponse;
use Nexus\Core\UnitOfWork\UpdatedResources;

/**
 * UseItemResponse
 *
 * アイテム使用APIのレスポンス
 */
class UseItemResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $mstItemId,
        public readonly string $effect,
        public readonly int $itemUsed,
        public readonly int $appliedValue,
        public readonly UpdatedResources $updatedResources,
    ) {}

    /**
     * レスポンスを生成
     */
    public function toArray(): array
    {
        return [
            'mst_item_id' => $this->mstItemId,
            'effect' => $this->effect,
            'item_used' => $this->itemUsed,
            'applied_value' => $this->appliedValue,
            // この処理で更新された trx（trx_item_list、trx_stamina_list、sys_player など）
            'updated_resources' => $this->updatedResources->toResponseArray(),
        ];
    }
}
