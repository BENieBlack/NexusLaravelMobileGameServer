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
        return $this->findCachedModel($trxWalletBalanceId);
    }

    /**
     * 残高を取得（FIFO順：有償優先 → 有効期限が近いものから）
     *
     * このリクエストで消費して、まだDBに反映していないモデルを使う。
     * DBだけを見ると、同じ通貨を同じトランザクションで続けて消費したときに、
     * 2回目が1回目の消費を見ずに同じ残高から引いてしまい、内訳（残高）が現在値より多く残る。
     *
     * @param  string  $mstItemId  アイテムID
     * @return CustomCollection<int, TrxWalletBalance>
     */
    public function selectAllBalancesByMstItemId(string $mstItemId): CustomCollection
    {
        $balances = $this->persistedBalancesOf($mstItemId)
            ->filter(fn (TrxWalletBalance $balance) => $balance->getCurrentAmount() > 0)
            ->all();

        // 優先順位: is_paid DESC (有償優先), expire_at ASC (NULLは最後), id ASC
        usort($balances, fn (TrxWalletBalance $a, TrxWalletBalance $b) => [$b->getIsPaid(), $a->getExpireAt() === null, $a->getExpireAt(), $a->getId()]
            <=> [$a->getIsPaid(), $b->getExpireAt() === null, $b->getExpireAt(), $b->getId()]);

        return new CustomCollection($balances);
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
        $nowString = $now->format('Y-m-d H:i:s');

        $expired = $this->persistedBalancesOf($mstItemId)
            ->filter(fn (TrxWalletBalance $balance) => $balance->getCurrentAmount() > 0
                && $balance->getExpireAt() !== null
                && $balance->getExpireAt() < $nowString)
            ->all();

        return new CustomCollection(array_values($expired));
    }

    /**
     * メモリ（DBから読んだ行と、このリクエストでの更新）にある、DBに行がある残高
     *
     * 新規に積んだだけの残高（IDがまだ無い）は、更新の対象にできないので含めない。
     *
     * @return CustomCollection<int, TrxWalletBalance>
     */
    private function persistedBalancesOf(string $mstItemId): CustomCollection
    {

        return $this->queryOrMemory()
            ->filter(fn (TrxWalletBalance $balance) => $balance->exists && $balance->getMstItemId() === $mstItemId)
            ->values();
    }
}
