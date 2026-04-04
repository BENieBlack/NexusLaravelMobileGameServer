<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWalletBalance;
use App\Persistence\ApiSession;
use Illuminate\Support\Collection;

/**
 * TrxWalletBalanceRepository
 *
 * 通貨残高管理Repository（取得単位）
 * FIFO方式で消費し、有効期限管理を可能にする
 *
 * FIFO優先順位:
 * 1. expire_at ASC (有効期限が近いものから、NULLは最後)
 * 2. id ASC (古い取得から)
 * 
 * @extends _BaseTrxRepository<TrxWalletBalance>
 */
class TrxWalletBalanceRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxWalletBalance::class;

    /**
     * 残高を取得（FIFO順：有効期限が近いものから）
     * 
     * @param string $mstItemId アイテムID
     * @return Collection<int, TrxWalletBalance>
     */
    public function findAllBalancesByMstItemId(string $mstItemId): Collection
    {
        $sysPlayerId = $this->getSysPlayerId();
        
        // DBから取得（FIFO順）
        // 優先順位: expire_at ASC (NULLは最後), id ASC
        return TrxWalletBalance::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->where('current_amount', '>', 0)
            ->orderByRaw('expire_at IS NULL, expire_at ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * 有効期限切れの残高を取得
     * 
     * @param string $mstItemId アイテムID
     * @param \Carbon\CarbonImmutable $now 現在時刻
     * @return Collection<int, TrxWalletBalance>
     */
    public function findAllExpiredBalancesByMstItemId(string $mstItemId, \Carbon\CarbonImmutable $now): Collection
    {
        $sysPlayerId = $this->getSysPlayerId();
        
        // DBから取得
        return TrxWalletBalance::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->where('current_amount', '>', 0)
            ->where('expire_at', '<', $now)
            ->get();
    }
}
