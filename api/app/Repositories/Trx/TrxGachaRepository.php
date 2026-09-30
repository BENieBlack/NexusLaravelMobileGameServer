<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGacha;

/**
 * TrxGachaRepository
 *
 * ガチャプレイヤー進行状況Repository
 *
 * 常にEloquent Modelを返す。DTOが必要な箇所は
 * GachaProgressRepositoryAdapterを経由すること。
 *
 * @extends _BaseTrxRepository<TrxGacha>
 */
class TrxGachaRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxGacha::class;

    /**
     * プレイヤーと対象ガチャの進行状況を取得
     *
     * 生クエリではなくキャッシュ経由で引く。
     * 直前に insertProgress() で積んだ未フラッシュの行も拾えるようにするため。
     */
    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?TrxGacha
    {
        /** @var TrxGacha|null */
        return $this->selectMapBySysPlayerId($sysPlayerId)
            ->firstWhere('mst_gacha_id', $mstGachaId);
    }

    /**
     * ガチャ進行状況を新規登録キューに積む
     */
    public function insertProgress(
        int $sysPlayerId,
        string $mstGachaId,
        int $currentStep,
        int $dailyDrawCount,
        mixed $dailyResetAt,
        int $totalDrawCount,
        mixed $totalResetAt
    ): TrxGacha {
        $progress = new TrxGacha([
            'sys_player_id' => $sysPlayerId,
            'mst_gacha_id' => $mstGachaId,
            'current_step' => $currentStep,
            'daily_draw_count' => $dailyDrawCount,
            'daily_reset_at' => $dailyResetAt,
            'total_draw_count' => $totalDrawCount,
            'total_reset_at' => $totalResetAt,
        ]);
        $progress->exists = false;
        $this->setModel($progress);

        return $progress;
    }
}
