<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;

/**
 * TrxStaminaRepository
 * 
 * スタミナデータの永続化のみを担当
 * ビジネスロジックはStaminaServiceに実装
 * 
 * @extends _BaseTrxRepository<TrxStamina>
 */
class TrxStaminaRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxStamina::class;
    protected string $selectKey = 'sys_player_id';

    /**
     * プレイヤーのスタミナ情報を取得
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @return TrxStamina|null
     */
    public function selectBySysPlayerId(): ?TrxStamina
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $modelCollection = $this->queryOrMemory();
        
        // スタミナは通常1プレイヤーに1レコードなのでfirst()で取得
        /** @var TrxStamina|null */
        return $modelCollection->first();
    }
}
