<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxTradeProduct;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxTradeProductRepository
 *
 * 交換所の商品ごとの交換回数の永続化のみを担当
 * ビジネスロジックは NexusTrade\Services\TradeService に実装
 *
 * @extends _BaseTrxRepository<TrxTradeProduct>
 */
class TrxTradeProductRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxTradeProduct::class;

    /**
     * プレイヤーの交換回数を全件取得する
     *
     * @return CustomCollection<array-key, TrxTradeProduct>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * 商品IDで1件取得する
     */
    public function selectByMstTradeProductId(string $mstTradeProductId): ?TrxTradeProduct
    {
        /** @var TrxTradeProduct|null */
        return $this->queryOrMemory()->firstWhere('mst_trade_product_id', $mstTradeProductId);
    }

    /**
     * 交換回数を保存する（行が無ければ作る。書き込みはQueryManager）
     *
     * @param  string  $lastTradedAt  最後に交換した日時（Y-m-d H:i:s形式）
     */
    public function upsertTradeCount(int $sysPlayerId, string $mstTradeProductId, int $tradeCount, string $lastTradedAt): TrxTradeProduct
    {
        $trxTradeProduct = $this->selectByMstTradeProductId($mstTradeProductId);

        if ($trxTradeProduct === null) {
            $trxTradeProduct = new TrxTradeProduct([
                'sys_player_id' => $sysPlayerId,
                'mst_trade_product_id' => $mstTradeProductId,
                'is_delete' => false,
            ]);
            $trxTradeProduct->exists = false;
        }

        $trxTradeProduct->setTradeCount($tradeCount);
        $trxTradeProduct->setLastTradedAt($lastTradedAt);

        $this->setModel($trxTradeProduct);

        return $trxTradeProduct;
    }
}
