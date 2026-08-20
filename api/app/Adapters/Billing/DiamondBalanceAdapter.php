<?php

namespace App\Adapters\Billing;

use App\Models\Trx\TrxDiamond;
use NexusResource\DataTransferObjects\DiamondBalance;

/**
 * DiamondBalanceAdapter
 *
 * TrxDiamond Model と DiamondBalance の変換を行うアダプター
 */
class DiamondBalanceAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxDiamond $model): DiamondBalance
    {
        return new DiamondBalance(
            sysPlayerId: $model->getAttribute('sys_player_id'),
            platform: $model->getAttribute('platform'),
            paidAmount: $model->getPaidAmount(),
            freeAmount: $model->getFreeAmount(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxDiamond>  $models
     * @return array<DiamondBalance>
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
