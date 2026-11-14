<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamondBalance;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxDiamondBalanceRepository
 *
 * ダイヤモンド残高管理Repository（購入単位）
 * 先入先出（FIFO）方式で消費し、返金計算を可能にする
 *
 * @extends _BaseTrxRepository<TrxDiamondBalance>
 */
class TrxDiamondBalanceRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxDiamondBalance::class;

    /**
     * 消費の対象になる購入単位の行を、古い順に返す（FIFO）
     *
     * 条件: platform が引数と一致（billing_platform は見ない）・current_amount > 0。論理削除（is_delete = true）の行は
     * 明示のフィルタではなく、queryOrMemory() が読まないことに頼って除外する。
     * 並び: created_at 昇順、同じ秒は id 昇順。id は作成順を表さない（TiDB モードでは UUID）ので、
     * 同点を決めるためだけに使う。
     *
     * このリクエストで setModel() 済みの行（消費の途中結果・購入で作った未保存の行）を DB の行より
     * 優先して見る。そのため、同じ
     * トランザクションで 2 回消費しても、2 回目は 1 回目に減らした current_amount から計算できる。
     *
     * @return CustomCollection<int, TrxDiamondBalance>
     */
    public function selectConsumableByPlatform(int $sysPlayerId, string $platform): CustomCollection
    {
        // DB（キャッシュ）の行に、このリクエストで setModel() 済みの行を重ねる。
        // 購入で作った未保存の行は、キャッシュが空の状態で setModel() されると
        // queryOrMemory() の読み直しで落ちることがあるため、キューからも拾う。
        // id がある行はキュー側のモデルで置き換える（同じ行の最新の状態）。id が無い行（未保存）は追加する
        $candidates = [];
        foreach ($this->queryOrMemory() as $model) {
            $candidates[$model->getKey() === null ? 'object:'.spl_object_id($model) : 'id:'.$model->getKey()] = $model;
        }
        foreach ($this->getQueuedModels() as $model) {
            $candidates[$model->getKey() === null ? 'object:'.spl_object_id($model) : 'id:'.$model->getKey()] = $model;
        }

        $rows = array_values(array_filter(
            $candidates,
            fn (TrxDiamondBalance $balance) => $balance->getSysPlayerId() === $sysPlayerId
                && $balance->getPlatform() === $platform
                && $balance->getCurrentAmount() > 0
        ));

        // 未保存の行（created_at が null）は最後に回す。文字列比較で足りる（'Y-m-d H:i:s'）
        usort($rows, fn (TrxDiamondBalance $a, TrxDiamondBalance $b) => [$a->getCreatedAt() ?? '9999-12-31 23:59:59', $a->getKey() ?? PHP_INT_MAX]
            <=> [$b->getCreatedAt() ?? '9999-12-31 23:59:59', $b->getKey() ?? PHP_INT_MAX]);

        /** @var CustomCollection<int, TrxDiamondBalance> */
        return new CustomCollection($rows);
    }
}
