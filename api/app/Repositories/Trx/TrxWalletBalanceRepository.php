<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWalletBalance;
use Carbon\CarbonImmutable;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxWalletBalanceRepository
 *
 * 通貨残高管理Repository（取得単位）
 * FIFO方式で消費し、有効期限管理を可能にする
 *
 * FIFO優先順位（有償優先消費）:
 * 1. is_paid DESC (有償を優先的に消費)
 * 2. expire_at ASC (有効期限が近いものから、NULLは最後)
 * 3. id ASC (古い取得から)
 *
 * @extends _BaseTrxRepository<TrxWalletBalance>
 */
class TrxWalletBalanceRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxWalletBalance::class;

    /**
     * IDで残高を検索
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param  int  $trxWalletBalanceId  trx_wallet_balance.id
     * @return TrxWalletBalance|null 残高（見つからない場合はnull）
     */
    public function selectById(int $trxWalletBalanceId): ?TrxWalletBalance
    {
        // queryOrMemory()で全データをキャッシュにロード（内部の$sysPlayerIdを使用）
        $this->queryOrMemory();

        // キャッシュから取得
        return $this->getModel($trxWalletBalanceId);
    }

    /**
     * 残高を取得（FIFO順：有償優先 → 有効期限が近いものから）
     *
     * @param  string  $mstItemId  アイテムID
     * @return CustomCollection<int, TrxWalletBalance>
     */
    public function selectAllBalancesByMstItemId(string $mstItemId): CustomCollection
    {
        $sysPlayerId = $this->getSysPlayerId();

        // DBから取得（FIFO順、有償優先）
        // 優先順位: is_paid DESC (有償優先), expire_at ASC (NULLは最後), id ASC
        $results = TrxWalletBalance::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->where('current_amount', '>', 0)
            ->orderBy('is_paid', 'DESC')
            ->orderByRaw('expire_at IS NULL, expire_at ASC')
            ->orderBy('id', 'ASC')
            ->get();

        // CustomCollectionに変換
        return new CustomCollection($results->all());
    }

    /**
     * 有効期限切れの残高を取得
     *
     * @param  string  $mstItemId  アイテムID
     * @param  CarbonImmutable  $now  現在時刻
     * @return CustomCollection<int, TrxWalletBalance>
     */
    public function selectAllExpiredBalancesByMstItemId(string $mstItemId, CarbonImmutable $now): CustomCollection
    {
        $sysPlayerId = $this->getSysPlayerId();

        // DBから取得
        $results = TrxWalletBalance::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->where('current_amount', '>', 0)
            ->where('expire_at', '<', $now)
            ->get();

        // CustomCollectionに変換
        return new CustomCollection($results->all());
    }
}
