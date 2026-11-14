<?php

namespace App\Domain\Trade\Constants;

/**
 * TradeCostType
 *
 * 交換所の商品の消費種別（mst_trade_product.cost_type）
 *
 * 消費する個数は cost_amount、アイテム・通貨の種類は cost_mst_id で指定する。
 * item / wallet は報酬の content_type と揃えている。diamond / paid_diamond は消費だけの種別で、
 * 報酬の content_type には無い（無償ダイヤの報酬は item + free_diamond。設計 free-diamond-item）
 */
class TradeCostType
{
    /** ダイヤ（無償 → 有償の順に消費） */
    public const DIAMOND = 'diamond';

    /** 有償ダイヤだけ */
    public const PAID_DIAMOND = 'paid_diamond';

    /** アイテム（cost_mst_id に mst_item.id） */
    public const ITEM = 'item';

    /** コイン・ゴールドなどの通貨（cost_mst_id に mst_item.id。mst_item.is_wallet のもの） */
    public const WALLET = 'wallet';
}
