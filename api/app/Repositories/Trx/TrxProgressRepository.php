<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxProgress;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxProgressRepository
 *
 * 出来事 × 対象ごとの通算カウンタの永続化のみを担当
 * ビジネスロジックは NexusMission\Services\MissionService に実装
 *
 * @extends _BaseTrxRepository<TrxProgress>
 */
class TrxProgressRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxProgress::class;

    /**
     * プレイヤーのカウンタを全件取得する
     *
     * @return CustomCollection<array-key, TrxProgress>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * 出来事と対象で1件取得する
     */
    public function selectByTriggerTypeAndSubTargetId(string $triggerType, string $subTargetId): ?TrxProgress
    {
        /** @var TrxProgress|null */
        return $this->queryOrMemory()
            ->where('trigger_type', $triggerType)
            ->firstWhere('sub_target_id', $subTargetId);
    }

    /**
     * カウンタの値を保存する（行が無ければ作る。書き込みはQueryManager）
     */
    public function upsertProgressValue(int $sysPlayerId, string $triggerType, string $subTargetId, int $progressValue): TrxProgress
    {
        $trxProgress = $this->selectByTriggerTypeAndSubTargetId($triggerType, $subTargetId);

        if ($trxProgress === null) {
            $trxProgress = new TrxProgress([
                'sys_player_id' => $sysPlayerId,
                'trigger_type' => $triggerType,
                'sub_target_id' => $subTargetId,
                'is_delete' => false,
            ]);
            $trxProgress->exists = false;
        }

        $trxProgress->setProgressValue($progressValue);
        $this->setModel($trxProgress);

        return $trxProgress;
    }
}
