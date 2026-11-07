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
    public static function toDto(SysPlayer $sysPlayer): PlayerVip
    {
        return new PlayerVip(
            sysPlayerId: $sysPlayer->getId(),
            vipPoint: $sysPlayer->getVipPoint(),
            totalPaidAmount: $sysPlayer->getTotalPaidAmount()
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysPlayer>  $sysPlayers
     * @return array<PlayerVip>
     */
    public static function toDtoArray(iterable $sysPlayers): array
    {
        $dtos = [];
        foreach ($sysPlayers as $sysPlayer) {
            $dtos[] = self::toDto($sysPlayer);
        }

        return $dtos;
    }
}
