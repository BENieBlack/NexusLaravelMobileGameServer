<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxEquipment;
use NexusUtilities\ClockUtility;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * TrxEquipmentRepository
 *
 * プレイヤーが所持する装備を管理するRepository
 * 
 * @extends _BaseTrxRepository<TrxEquipment>
 */
class TrxEquipmentRepository extends _BaseTrxRepository
{
    /** @var class-string<TrxEquipment> */
    protected string $modelClass = TrxEquipment::class;

    /**
     * IDで装備を検索
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param int $trxEquipmentId trx_equipment.id（プレイヤー所有装備）
     * @return TrxEquipment|null 装備（見つからない場合はnull）
     */
    public function selectById(int $trxEquipmentId): ?TrxEquipment
    {
        // queryOrMemory()で全データをキャッシュにロード（内部の$sysPlayerIdを使用）
        $this->queryOrMemory();
        
        // キャッシュから取得
        return $this->getModel($trxEquipmentId);
    }

    /**
     * 新規装備を作成
     *
     * @param string $mstEquipmentId 装備マスターID
     * @param int|null $level 初期レベル（nullの場合は1）
     * @param int|null $grade 初期グレード（nullの場合は1）
     * @return TrxEquipment 作成された装備
     */
    public function createEquipment(
        string $mstEquipmentId,
        ?int $level = null,
        ?int $grade = null
    ): TrxEquipment {
        $sysPlayerId = \App\Persistence\ApiSession::getSysPlayerId();
        
        $trxEquipment = new TrxEquipment([
            'sys_player_id' => $sysPlayerId,
            'mst_equipment_id' => $mstEquipmentId,
            'grade' => $grade ?? 1,
            'level' => $level ?? 1,
            'created_at' => ClockUtility::now(),
            'updated_at' => ClockUtility::now(),
        ]);

        // setModelでTrxデータをキューイング
        $this->setModel($trxEquipment);

        return $trxEquipment;
    }
}
