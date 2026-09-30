<?php

namespace App\Adapters\Player;

use App\Models\Sys\SysPlayer;
use NexusVip\DataTransferObjects\PlayerVip;

/**
 * PlayerVipAdapter
 *
 * SysPlayer Model と PlayerVip の変換を行うアダプター
 */
class PlayerVipAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysPlayer $model): PlayerVip
    {
        return new PlayerVip(
            sysPlayerId: $model->getId(),
            vipPoint: $model->getVipPoint(),
            totalPaidAmount: $model->getTotalPaidAmount()
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysPlayer>  $models
     * @return array<PlayerVip>
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
