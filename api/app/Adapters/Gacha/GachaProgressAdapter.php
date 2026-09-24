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
    public static function toDto(TrxGacha $model): GachaProgress
    {
        return new GachaProgress(
            sysPlayerId: $model->sys_player_id,
            mstGachaId: $model->mst_gacha_id,
            currentStep: $model->current_step,
            dailyDrawCount: $model->daily_draw_count,
            dailyResetAt: $model->daily_reset_at,
            totalDrawCount: $model->total_draw_count,
            totalResetAt: $model->total_reset_at
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxGacha>  $models
     * @return array<GachaProgress>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];
        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
