<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWallet;

/**
 * TrxWalletRepository
 *
 * 汎用通貨現在値管理Repository
 * 複合主キー: (sys_player_id, mst_item_id)
 *
 * Gold, EventCoin, RaidMedal, PvPPoint, GvGPoint等を統合管理
 *
 * @extends _BaseTrxRepository<TrxWallet>
 */
class TrxWalletRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxWallet::class;

    /**
     * プレイヤーIDとアイテムIDでウォレットを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     */
    public function selectByMstItemId(int $sysPlayerId, string $mstItemId): ?TrxWallet
    {

        // メモリ内キューから検索
        $queue = $this->queryOrMemory();
        $found = $queue->first(function ($model) use ($sysPlayerId, $mstItemId) {
            return $model->sys_player_id === $sysPlayerId
                && $model->mst_item_id === $mstItemId;
        });

        if ($found) {
            return $found;
        }

        // DBから検索
        return TrxWallet::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();
    }
}
