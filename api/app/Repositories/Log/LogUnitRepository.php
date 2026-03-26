<?php

namespace App\Repositories\Log;

use App\Models\Log\LogUnit;
use App\Utilities\Clock;

/**
 * LogUnitRepository
 *
 * ユニット（キャラクター）のログを管理するRepository
 * 通常のログなので isPurchaseLog = false（デフォルト）
 */
class LogUnitRepository extends _BaseLogRepository
{
    protected string $modelClass = LogUnit::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * ユニットログを記録（Unit of Work パターン使用）
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param int $trxUnitId
     * @param int $mstUnitId
     * @param int $beforeGrade
     * @param int $afterGrade
     * @param int $beforeLevel
     * @param int $afterLevel
     * @param int $beforeLevelExp
     * @param int $afterLevelExp
     * @return void
     */
    public function createUnitLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $trxUnitId,
        int $mstUnitId,
        int $beforeGrade,
        int $afterGrade,
        int $beforeLevel,
        int $afterLevel,
        int $beforeLevelExp,
        int $afterLevelExp
    ): void {
        $model = new LogUnit([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'trx_unit_id' => $trxUnitId,
            'mst_unit_id' => $mstUnitId,
            'before_grade' => $beforeGrade,
            'after_grade' => $afterGrade,
            'before_level' => $beforeLevel,
            'after_level' => $afterLevel,
            'before_level_exp' => $beforeLevelExp,
            'after_level_exp' => $afterLevelExp,
            'system_at' => Clock::now(),
            'created_at' => Clock::now(),
        ]);

        $this->setModel($model);
    }
}
