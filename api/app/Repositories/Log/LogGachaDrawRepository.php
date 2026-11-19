<?php

namespace App\Repositories\Log;

use App\Models\Log\LogGachaDraw;
use Nexus\Core\Utilities\ClockUtility;

/**
 * LogGachaDrawRepository
 *
 * ガチャ実行ログ（log_action_gacha_draw）を管理するRepository。
 * 履歴は trx ではなく log へ積む。書き込みは UseCase のトランザクション終了時に
 * ログイン中プレイヤーのシャードに対応する LogDB へ反映される（接続はモデルが解決する）。
 *
 * @extends _BaseLogRepository<LogGachaDraw>
 */
class LogGachaDrawRepository extends _BaseLogRepository
{
    protected string $modelClass = LogGachaDraw::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * ガチャ実行ログを積む
     *
     * @param  array<int, array<string, mixed>>  $prizes  抽選結果（result 列に JSON で保存する）
     */
    public function insertDrawLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $mstGachaId,
        int $drawCount,
        string $costType,
        ?string $costMstId,
        int $costAmount,
        array $prizes,
    ): void {
        $model = new LogGachaDraw([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'mst_gacha_id' => $mstGachaId,
            'draw_count' => $drawCount,
            'cost_type' => $costType,
            'cost_mst_id' => $costMstId,
            'cost_amount' => $costAmount,
            'result' => json_encode($prizes, JSON_UNESCAPED_UNICODE),
            'system_at' => ClockUtility::now(),
        ]);

        $this->setModel($model);
    }
}
