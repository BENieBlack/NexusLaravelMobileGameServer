<?php

namespace App\Http\Responses\Item;

use App\Http\Responses\_BaseResponse;

/**
 * UseResponse
 *
 * アイテム使用APIのレスポンス
 */
class UseResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $mstItemId,
        public readonly string $effect,
        public readonly int $itemUsed,
        public readonly int $appliedValue,
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
        ];
    }
}
