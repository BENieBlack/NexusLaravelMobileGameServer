<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxMission;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxMissionRepository
 *
 * ミッションの受け取り記録の永続化のみを担当
 * ビジネスロジックは NexusMission\Services\MissionService に実装
 *
 * @extends _BaseTrxRepository<TrxMission>
 */
class TrxMissionRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxMission::class;

    /**
     * プレイヤーの受け取り記録を全件取得する
     *
     * @return CustomCollection<array-key, TrxMission>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * ミッションIDで1件取得する
     */
    public function selectByMstMissionId(string $mstMissionId): ?TrxMission
    {
        /** @var TrxMission|null */
        return $this->queryOrMemory()->firstWhere('mst_mission_id', $mstMissionId);
    }

    /**
     * 受け取り記録を1件追加する（キューに積むだけ。書き込みはQueryManager）
     */
    public function insertReceipt(int $sysPlayerId, string $mstMissionId, string $receivedAt): TrxMission
    {
        $trxMission = new TrxMission([
            'sys_player_id' => $sysPlayerId,
            'mst_mission_id' => $mstMissionId,
            'received_at' => $receivedAt,
            'is_delete' => false,
        ]);
        $trxMission->exists = false;

        $this->setModel($trxMission);

        return $trxMission;
    }
}
