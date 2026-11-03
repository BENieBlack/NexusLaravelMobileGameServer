<?php

namespace NexusTrade\Contracts;

/**
 * TradeCostPayerInterface
 *
 * 商品のコストを支払う。何で支払えるか（ダイヤ・アイテム・通貨など）はアプリが決める
 */
interface TradeCostPayerInterface
{
    /**
     * コストを支払う。足りなければ例外を投げる（例外の種類はアプリが決める）
     *
     * @param  string  $costType  mst_trade_product.cost_type
     * @param  string|null  $costMstId  mst_trade_product.cost_mst_id
     * @param  int  $amount  支払う合計（コスト × 個数）
     */
    public function pay(int $sysPlayerId, string $costType, ?string $costMstId, int $amount): void;
}
