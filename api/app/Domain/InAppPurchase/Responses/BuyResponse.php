<?php

namespace App\Domain\InAppPurchase\Responses;

use App\Domain\_BaseResponse;
use App\Domain\ReturnsUpdatedResources;
use App\Traits\HasUpdatedResources;

class BuyResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use HasUpdatedResources;

    /**
     * 応答は updated_resources と rewards（Pack/Pass のときだけ）だけ。
     * 購入した有償ダイヤの量・残高は載せない。有償は updated_resources.trx_diamond、
     * パックの無償ダイヤは updated_resources.trx_item_list の free_diamond で分かる
     * （設計 free-diamond-item の決定 B）。
     *
     * @param  array<int, array<string, mixed>>  $rewards  付与されたアイテムやユニット（Pack/Passの場合）
     */
    public function __construct(
        public readonly array $rewards = [],
    ) {}

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];

        if (! empty($this->rewards)) {
            $response['rewards'] = $this->rewards;
        }

        return $response;
    }
}
