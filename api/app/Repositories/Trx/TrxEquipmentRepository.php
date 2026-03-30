<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxEquipment;
use App\Utilities\Clock;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * TrxEquipmentRepository
 *
 * プレイヤーが所持する装備を管理するRepository
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
}
