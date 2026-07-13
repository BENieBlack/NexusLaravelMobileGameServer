<?php

namespace App\Repositories\Log;

use App\Models\Log\LogEquipment;
use NexusUtilities\ClockUtility;

/**
 * LogEquipmentRepository
 *
 * 装備のログを管理するRepository
 * 通常のログなので isPurchaseLog = false（デフォルト）
 * 
 * @extends _BaseLogRepository<LogEquipment>
 */
class LogEquipmentRepository extends _BaseLogRepository
{
    protected string $modelClass = LogEquipment::class;

    /**
     * ユニークキー（ログはIDで管理）
     *
     * @var array<string>
     */
    protected array $uniqueKeys = ['id'];

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * 装備ログを記録（Unit of Work パターン使用）
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param int $trxEquipmentId
     * @param string $mstEquipmentId
     * @param int $beforeGrade
     * @param int $afterGrade
     * @param int $beforeLevel
     * @param int $beforeLevelExp
     * @param int $afterLevel
     * @param int $afterLevelExp
     * @return void
     */
    public function createEquipmentLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $trxEquipmentId,
        string $mstEquipmentId,
        int $beforeGrade,
        int $afterGrade,
        int $beforeLevel,
        int $beforeLevelExp,
        int $afterLevel,
        int $afterLevelExp
    ): void {
        $logEquipment = new LogEquipment([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'trx_equipment_id' => $trxEquipmentId,
            'mst_equipment_id' => $mstEquipmentId,
            'before_grade' => $beforeGrade,
            'after_grade' => $afterGrade,
            'before_level' => $beforeLevel,
            'before_level_exp' => $beforeLevelExp,
            'after_level' => $afterLevel,
            'after_level_exp' => $afterLevelExp,
            'system_at' => ClockUtility::now(),
            'created_at' => ClockUtility::now(),
        ]);

        $this->setModel($logEquipment);
    }
}
