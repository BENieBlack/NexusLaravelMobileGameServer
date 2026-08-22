<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxEquipment;
use App\Persistence\ApiSession;

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
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @return TrxEquipment|null 装備（見つからない場合はnull）
     */
    public function selectById(int $trxEquipmentId): ?TrxEquipment
    {
        // queryOrMemory()で全データをキャッシュにロード（内部の$sysPlayerIdを使用）
        $this->queryOrMemory();

        // キャッシュから取得
        return $this->findCachedModel($trxEquipmentId);
    }

    /**
     * ログイン中のプレイヤーに新規装備を作成
     *
     * @param  string  $mstEquipmentId  装備マスターID
     * @param  int|null  $level  初期レベル（nullの場合は1）
     * @param  int|null  $grade  初期グレード（nullの場合は1）
     * @return TrxEquipment 作成された装備
     */
    public function insertEquipment(
        string $mstEquipmentId,
        ?int $level = null,
        ?int $grade = null
    ): TrxEquipment {
        return $this->insertEquipmentForPlayer(ApiSession::getSysPlayerId(), $mstEquipmentId, $level, $grade);
    }

    /**
     * 指定したプレイヤーに新規装備を作成
     *
     * 配送はログインセッションの本人以外（運営からの一斉配布など）にも走るため、
     * 付与先を明示できる入口を用意している。
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  string  $mstEquipmentId  装備マスターID
     * @param  int|null  $level  初期レベル（nullの場合は1）
     * @param  int|null  $grade  初期グレード（nullの場合は1）
     * @return TrxEquipment 作成された装備
     */
    public function insertEquipmentForPlayer(
        int $sysPlayerId,
        string $mstEquipmentId,
        ?int $level = null,
        ?int $grade = null
    ): TrxEquipment {
        $trxEquipment = new TrxEquipment([
            'sys_player_id' => $sysPlayerId,
            'mst_equipment_id' => $mstEquipmentId,
            'grade' => $grade ?? 1,
            'level' => $level ?? 1,
        ]);

        // setModelでTrxデータをキューイング
        $this->setModel($trxEquipment);

        return $trxEquipment;
    }
}
