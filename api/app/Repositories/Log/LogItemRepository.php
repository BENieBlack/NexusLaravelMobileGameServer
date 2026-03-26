<?php

namespace App\Repositories\Log;

use App\Models\Log\LogItem;
use App\Utilities\Clock;
use Illuminate\Support\Collection;

/**
 * LogItemRepository
 *
 * アイテム変更ログを管理するRepository
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
     * @param string $uniqueRequestId リクエスト一意ID
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId アイテムマスターID
     * @param int $beforeAmount 変更前数量
     * @param int $afterAmount 変更後数量
     * @return void
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
            'system_at' => Clock::now(),
            'created_at' => Clock::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }

    /**
     * 特定アイテムのログを取得
     *
     * @param string $mstItemId アイテムマスターID
     * @return Collection<int, LogItem>
     */
    public function findAllByMstItemId(string $mstItemId): Collection
    {
        return $this->queryOrMemory()
            ->where('mst_item_id', $mstItemId)
            ->sortByDesc('system_at')
            ->values();
    }

    /**
     * リクエストIDでアイテムログを取得
     *
     * @param string $uniqueRequestId
     * @return LogItem|null
     */
    public function findByUniqueRequestId(string $uniqueRequestId): ?LogItem
    {
        return $this->queryOrMemory()
            ->where('unique_request_id', $uniqueRequestId)
            ->first();
    }
}
