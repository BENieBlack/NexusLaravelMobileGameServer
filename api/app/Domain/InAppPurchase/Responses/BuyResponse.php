<?php

namespace App\Domain\InAppPurchase\Responses;

use App\Domain\_BaseResponse;
use App\Traits\HasUpdatedResources;

class BuyResponse extends _BaseResponse
{
    use HasUpdatedResources;

    /**
     * @param  int  $paidDiamondAmount  購入した有償ダイヤモンド数
     * @param  array<int, array<string, mixed>>  $rewards  付与されたアイテムやユニット（Pack/Passの場合）
     */
    public function __construct(
        public readonly int $paidDiamondAmount,
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
            'paid_diamond_amount' => $this->paidDiamondAmount,
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];

        if (! empty($this->rewards)) {
            $response['rewards'] = $this->rewards;
        }

        return $response;
    }
}
