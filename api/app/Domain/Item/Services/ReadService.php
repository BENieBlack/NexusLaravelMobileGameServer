<?php

namespace App\Domain\Item\Services;

use App\Models\Trx\TrxItem;

/**
 * ReadService
 *
 * アイテム残高の読み取り専用サービス
 * 
 * Responsibilities:
 * - アイテム残高の取得（getItemAmount）
 * - 複数アイテム残高の一括取得（将来的に追加予定）
 * 
 * Characteristics:
 * - Read-only operations (no state changes)
 * - Query operations only
 * - Can be cached or optimized independently
 */
class ReadService
{
    /**
     * アイテムの所持数を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function getItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $trxItem ? $trxItem->getTotalAmount() : 0;
    }
}
