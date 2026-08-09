<?php

namespace App\Repositories\Log;

use App\Models\Log\LogItem;
use Nexus\Core\Support\CustomCollection;
use Nexus\Core\Utilities\ClockUtility;

/**
 * LogItemRepository
 *
 * アイテム変更ログを管理するRepository
 *
 * @extends _BaseLogRepository<LogItem>
 */
class LogItemRepository extends _BaseLogRepository
{
    protected string $modelClass = LogItem::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * アイテムログを記録（Unit of Work パターン使用）
     *
     * @param  string  $uniqueRequestId  リクエスト一意ID
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムマスターID
     * @param  int  $beforeAmount  変更前数量
     * @param  int  $afterAmount  変更後数量
     */
    public function createItemLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $mstItemId,
        int $beforeAmount,
        int $afterAmount
    ): void {
        $model = new LogItem([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'mst_item_id' => $mstItemId,
            'before_amount' => $beforeAmount,
            'after_amount' => $afterAmount,
            'system_at' => ClockUtility::now(),
            'created_at' => ClockUtility::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }

    /**
     * 特定アイテムのログを取得
     *
     * @param  string  $mstItemId  アイテムマスターID
     * @return CustomCollection<int, LogItem>
     */
    public function findAllByMstItemId(string $mstItemId): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('mst_item_id', $mstItemId)
            ->sortByDesc('system_at')
            ->values();
    }

    /**
     * リクエストIDでアイテムログを取得
     */
    public function findByUniqueRequestId(string $uniqueRequestId): ?LogItem
    {
        return $this->queryOrMemory()
            ->where('unique_request_id', $uniqueRequestId)
            ->first();
    }
}
