<?php

namespace App\Http\Responses\InAppPurchase;

use App\Domain\InAppPurchase\DataTransferObjects\BuyResult;
use App\Http\Responses\_BaseResponse;

class BuyResponse extends _BaseResponse
{
    /**
     * @param  int  $paidDiamondAmount  購入した有償ダイヤモンド数
     * @param  int  $totalPaidDiamondAmount  現在の総有償ダイヤモンド数
     * @param  int  $totalFreeDiamondAmount  現在の総無償ダイヤモンド数
     * @param  array<int, array<string, mixed>>  $rewards  付与されたアイテムやユニット（Pack/Passの場合）
     */
    public function __construct(
        public readonly int $paidDiamondAmount,
        public readonly int $totalPaidDiamondAmount,
        public readonly int $totalFreeDiamondAmount,
        public readonly array $rewards = [],
    ) {}

    /**
     * 購入結果からレスポンスを生成
     */
    public static function fromResult(BuyResult $result): self
    {
        return new self(
            paidDiamondAmount: $result->paidDiamondAmount,
            totalPaidDiamondAmount: $result->totalPaidDiamondAmount,
            totalFreeDiamondAmount: $result->totalFreeDiamondAmount,
            rewards: $result->rewards,
        );
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'paid_diamond_amount' => $this->paidDiamondAmount,
            'total_paid_diamond_amount' => $this->totalPaidDiamondAmount,
            'total_free_diamond_amount' => $this->totalFreeDiamondAmount,
        ];

        if (! empty($this->rewards)) {
            $response['rewards'] = $this->rewards;
        }

        return $response;
    }
}
