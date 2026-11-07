<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuildApply;
use NexusGuild\DataTransferObjects\GuildApply;

/**
 * GuildApplyAdapter
 *
 * SysGuildApply Model と GuildApply の変換を行うアダプター
 */
class GuildApplyAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuildApply $sysGuildApply): GuildApply
    {
        return new GuildApply(
            id: $sysGuildApply->getId(),
            sysGuildId: $sysGuildApply->getSysGuildId(),
            sysPlayerId: $sysGuildApply->getSysPlayerId(),
            status: $sysGuildApply->getStatus(),
            createdAt: $sysGuildApply->getCreatedAt(),
            updatedAt: $sysGuildApply->getUpdatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuildApply>  $sysGuildApplys
     * @return array<GuildApply>
     */
    public static function toDtoArray(iterable $sysGuildApplys): array
    {
        $dtos = [];
        foreach ($sysGuildApplys as $sysGuildApply) {
            $dtos[] = self::toDto($sysGuildApply);
        }

        return $dtos;
    }
}
