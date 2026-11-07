<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuild;
use NexusGuild\DataTransferObjects\Guild;

/**
 * GuildAdapter
 *
 * SysGuild Model と Guild の変換を行うアダプター
 */
class GuildAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuild $sysGuild): Guild
    {
        return new Guild(
            id: $sysGuild->getId(),
            name: $sysGuild->getName(),
            description: $sysGuild->getDescription() ?? '',
            level: $sysGuild->getLevel(),
            exp: $sysGuild->getExp(),
            maxMembers: $sysGuild->getMaxMembers(),
            currentMembers: $sysGuild->countMembers(),
            createdAt: $sysGuild->getCreatedAt(),
            updatedAt: $sysGuild->getUpdatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuild>  $sysGuilds
     * @return array<Guild>
     */
    public static function toDtoArray(iterable $sysGuilds): array
    {
        $dtos = [];
        foreach ($sysGuilds as $sysGuild) {
            $dtos[] = self::toDto($sysGuild);
        }

        return $dtos;
    }
}
