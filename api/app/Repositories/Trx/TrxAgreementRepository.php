<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxAgreement;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxAgreementRepository
 *
 * 利用規約の同意記録（trx_agreement）の永続化のみを担当
 * ビジネスロジックは NexusAgreement\Services\AgreementService に実装
 *
 * @extends _BaseTrxRepository<TrxAgreement>
 */
class TrxAgreementRepository extends _BaseTrxRepository
{
    /**
     * 同意記録は消さないため is_delete カラムを持たない
     */
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxAgreement::class;

    /**
     * プレイヤーの同意記録を全件取得する
     *
     * @return CustomCollection<array-key, TrxAgreement>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * 同意記録を1件追加する（キューに積むだけ。書き込みはQueryManager）
     *
     * @param  string  $consentedAt  同意日時（Y-m-d H:i:s形式）
     */
    public function insertConsent(int $sysPlayerId, int $sysAgreementId, string $consentedAt): TrxAgreement
    {
        $trxAgreement = new TrxAgreement([
            'sys_player_id' => $sysPlayerId,
            'sys_agreement_id' => $sysAgreementId,
            'consented_at' => $consentedAt,
            'created_at' => $consentedAt,
        ]);
        $trxAgreement->exists = false;

        $this->setModel($trxAgreement);

        return $trxAgreement;
    }
}
