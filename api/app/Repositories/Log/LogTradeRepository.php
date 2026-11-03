<?php

namespace App\Repositories\Log;

use App\Models\Log\LogTrade;
use Illuminate\Support\Str;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;

/**
 * LogTradeRepository
 *
 * 交換所で交換した記録を管理するRepository
 *
 * @extends _BaseLogRepository<LogTrade>
 */
class LogTradeRepository extends _BaseLogRepository
{
    protected string $modelClass = LogTrade::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * 1リクエストのログをまとめて追うためのID。未設定ならログごとに振る
     */
    private ?string $uniqueRequestId = null;

    public function setUniqueRequestId(string $uniqueRequestId): void
    {
        $this->uniqueRequestId = $uniqueRequestId;
    }

    /**
     * 交換1回分のログを積む（書き込みはQueryManager）
     *
     * @param  int  $quantity  交換した個数
     * @param  int  $costAmount  支払った合計（コスト × 個数）
     */
    public function insertTrade(
        int $sysPlayerId,
        string $mstTradeProductId,
        int $quantity,
        string $costType,
        ?string $costMstId,
        int $costAmount,
    ): LogTrade {
        $model = new LogTrade([
            'unique_request_id' => $this->uniqueRequestId ?? Str::uuid()->toString(),
            'sys_player_id' => $sysPlayerId,
            'mst_trade_product_id' => $mstTradeProductId,
            'quantity' => $quantity,
            'cost_type' => $costType,
            'cost_mst_id' => $costMstId,
            'cost_amount' => $costAmount,
            'system_at' => ClockUtility::nowToString(),
        ]);

        $this->setModel($model);

        return $model;
    }

    /**
     * プレイヤーの交換ログを取得する
     *
     * @return CustomCollection<int, LogTrade>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }
}
