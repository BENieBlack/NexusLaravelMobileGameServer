<?php

namespace App\Adapters\Player;

use App\Models\Sys\SysPlayer;
use Nexus\Core\DataTransferObjects\Player;

/**
 * PlayerAdapter
 *
 * SysPlayer Model と Player の変換を行うアダプター
 */
class PlayerAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysPlayer $sysPlayer): Player
    {
        return new Player(
            id: $sysPlayer->getId(),
            uuid: $sysPlayer->getUuid(),
            myId: $sysPlayer->getMyId(),
            name: $sysPlayer->getName() ?? '',
            level: $sysPlayer->getLevel(),
            levelExp: $sysPlayer->getLevelExp(),
            createdAt: $sysPlayer->getCreatedAt(),
            updatedAt: $sysPlayer->getUpdatedAt()
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysPlayer>  $sysPlayers
     * @return array<Player>
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
