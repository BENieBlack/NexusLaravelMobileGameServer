<?php

namespace Nexus\Core\Resource\Constants;

/**
 * 無償ダイヤに関する定数
 *
 * 無償ダイヤは trx_item の 1 行（mst_item_id = free_diamond）の free_amount で持つ。
 * Item コンテキスト（ItemService のガード）・Player コンテキスト（初期付与）・
 * InAppPurchase コンテキスト（消費）が同じ ID を参照するが、コンテキスト間で
 * 定数を直接参照できない（ModuleBoundaryTest）ので、パッケージ側に置く。
 */
final class FreeDiamondConst
{
    /**
     * 無償ダイヤの mst_item.id
     */
    public const MST_ITEM_ID = 'free_diamond';

    private function __construct() {}
}
