<?php

namespace App\Adapters\Gacha;

use App\Models\Trx\TrxGacha;
use NexusGacha\DataTransferObjects\GachaProgress;

/**
 * GachaProgressAdapter
 *
 * TrxGacha Model と GachaProgress の変換を行うアダプター
 */
class GachaProgressAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxGacha $trxGacha): GachaProgress
    {
        return new GachaProgress(
            sysPlayerId: $trxGacha->sys_player_id,
            mstGachaId: $trxGacha->mst_gacha_id,
            currentStep: $trxGacha->current_step,
            dailyDrawCount: $trxGacha->daily_draw_count,
            dailyResetAt: $trxGacha->daily_reset_at,
            totalDrawCount: $trxGacha->total_draw_count,
            totalResetAt: $trxGacha->total_reset_at
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxGacha>  $trxGachas
     * @return array<GachaProgress>
     */
    public static function toDtoArray(iterable $trxGachas): array
    {
        $dtos = [];
        foreach ($trxGachas as $trxGacha) {
            $dtos[] = self::toDto($trxGacha);
        }

        return $dtos;
    }
}
