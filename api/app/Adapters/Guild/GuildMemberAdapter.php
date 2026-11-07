<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuildMember;
use NexusGuild\DataTransferObjects\GuildMember;

/**
 * GuildMemberAdapter
 *
 * SysGuildMember Model と GuildMember の変換を行うアダプター
 */
class GuildMemberAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuildMember $sysGuildMember): GuildMember
    {
        return new GuildMember(
            id: $sysGuildMember->getId(),
            sysGuildId: $sysGuildMember->getSysGuildId(),
            sysPlayerId: $sysGuildMember->getSysPlayerId(),
            role: $sysGuildMember->getRole(),
            joinedAt: $sysGuildMember->getJoinedAt(),
            createdAt: $sysGuildMember->getCreatedAt(),
            updatedAt: $sysGuildMember->getUpdatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuildMember>  $sysGuildMembers
     * @return array<GuildMember>
     */
    public static function toDtoArray(iterable $sysGuildMembers): array
    {
        $dtos = [];
        foreach ($sysGuildMembers as $sysGuildMember) {
            $dtos[] = self::toDto($sysGuildMember);
        }

        return $dtos;
    }
}
