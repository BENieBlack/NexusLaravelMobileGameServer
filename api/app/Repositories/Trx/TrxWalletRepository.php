<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWallet;
use App\Utilities\ApiSession;

/**
 * TrxWalletRepository
 *
 * 汎用通貨現在値管理Repository
 * 複合主キー: (sys_player_id, mst_item_id)
 *
 * Gold, EventCoin, RaidMedal, PvPPoint, GvGPoint等を統合管理
 */
class TrxWalletRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxWallet::class;

    /**
     * プレイヤーIDとアイテムIDでウォレットを取得
     * 
     * @param string $mstItemId アイテムID
     * @return TrxWallet|null
     */
    public function selectByMstItemId(string $mstItemId): ?TrxWallet
    {
        $sysPlayerId = $this->getSysPlayerId();
        
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
