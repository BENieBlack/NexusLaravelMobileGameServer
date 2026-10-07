<?php

namespace App\Domain\InAppPurchase\DataTransferObjects;

/**
 * BuyResult
 *
 * アプリ内課金商品購入の結果
 */
class BuyResult
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
}
