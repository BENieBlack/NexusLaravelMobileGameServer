<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGacha;
use App\Persistence\ApiSession;

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
     * ユニークキー（sys_player_id, mst_gacha_id の複合キー）
     *
     * @var array<string>
     */
    /** @var list<string> */
    protected array $uniqueKeys = ['sys_player_id', 'mst_gacha_id'];

    public function __construct(
        private readonly ApiSession $apiSession
    ) {}

    /**
     * プレイヤーと対象ガチャの進行状況を取得
     */
    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?TrxGacha
    {
        /** @var TrxGacha|null */
        return TrxGacha::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_delete', false)
            ->first();
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
