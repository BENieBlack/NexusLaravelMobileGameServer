<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxItem;

/**
 * TrxItemRepository
 *
 * アイテム管理Repository
 * データアクセスのみを担当し、ビジネスロジックはServiceに委譲
 *
 * @extends _BaseTrxRepository<TrxItem>
 */
class TrxItemRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxItem::class;

    /**
     * プレイヤーIDとアイテムIDでアイテムを取得
     *
     * このリクエストで加算・消費して、まだDBに反映していないモデルを先に探す。
     * DBだけを見ると、同じアイテムを同じトランザクションで2回付与したときに、
     * 2回目が1回目の結果を見ずに古い値から計算して、1回目の分が消える。
     */
    public function selectByMstItemId(int $sysPlayerId, string $mstItemId): ?TrxItem
    {
        $found = $this->queryOrMemory()->first(
            fn (TrxItem $model) => $model->getSysPlayerId() === $sysPlayerId && $model->getMstItemId() === $mstItemId
        );

        if ($found) {
            return $found;
        }

        // 論理削除された行はキャッシュに載らないため、DBから探す
        return TrxItem::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();
    }
}
