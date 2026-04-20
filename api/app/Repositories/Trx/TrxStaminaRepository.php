<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;

/**
 * TrxStaminaRepository
 * 
 * スタミナデータの永続化のみを担当
 * ビジネスロジックはStaminaServiceに実装
 * 
 * PRIMARY KEY: (sys_player_id, type)
 * 
 * @extends _BaseTrxRepository<TrxStamina>
 */
class TrxStaminaRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxStamina::class;
    protected string $selectKey = 'sys_player_id';

    /**
     * プレイヤーの指定タイプのスタミナ情報を取得
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param string $type スタミナタイプ
     * @return TrxStamina|null
     */
    public function selectByType(string $type): ?TrxStamina
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $modelCollection = $this->queryOrMemory();
        
        // typeでフィルタして取得
        /** @var TrxStamina|null */
        return $modelCollection->where('type', $type)->first();
    }

    /**
     * プレイヤーの全てのスタミナ情報を取得
     *
     * @return \Illuminate\Support\Collection<int, TrxStamina>
     */
    public function selectAllByPlayer(): \Illuminate\Support\Collection
    {
        // queryOrMemory()で全データをキャッシュにロード
        return $this->queryOrMemory();
    }
}
