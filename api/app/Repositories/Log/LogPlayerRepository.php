<?php

namespace App\Repositories\Log;

use App\Models\Log\LogPlayer;
use NexusUtilities\ClockUtility;

/**
 * LogPlayerRepository
 *
 * プレイヤーのレベルアップなどのログを管理するRepository
 * 通常のログなので isPurchaseLog = false（デフォルト）
 * 
 * @extends _BaseLogRepository<LogPlayer>
 */
class LogPlayerRepository extends _BaseLogRepository
{
    protected string $modelClass = LogPlayer::class;

    /**
     * 通常ログであることを明示（デフォルト値だが明示的に記載）
     */
    protected bool $isPurchaseLog = false;

    /**
     * プレイヤーログを記録（Unit of Work パターン使用）
     * 通常ログなので設定に応じてトランザクション内/外で実行される
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param int $beforeLevel
     * @param int $beforeLevelExp
     * @param int $afterLevel
     * @param int $afterLevelExp
     * @return void
     */
    public function createPlayerLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $beforeLevel,
        int $beforeLevelExp,
        int $afterLevel,
        int $afterLevelExp
    ): void {
        $model = new LogPlayer([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'before_level' => $beforeLevel,
            'before_level_exp' => $beforeLevelExp,
            'after_level' => $afterLevel,
            'after_level_exp' => $afterLevelExp,
            'system_at' => ClockUtility::now(),
            'created_at' => ClockUtility::now(),
        ]);

        // 通常ログとして登録
        $this->setModel($model);
    }
}
