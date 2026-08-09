<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuildMember;
use NexusGuild\Dto\GuildMemberDto;

/**
 * GuildMemberAdapter
 *
 * SysGuildMember Model と GuildMemberDto の変換を行うアダプター
 */
class GuildMemberAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuildMember $model): GuildMemberDto
    {
        return new GuildMemberDto(
            id: $model->getId(),
            sysGuildId: $model->getSysGuildId(),
            sysPlayerId: $model->getSysPlayerId(),
            role: $model->getRole(),
            joinedAt: $model->getJoinedAt(),
            createdAt: $model->created_at->format('Y-m-d H:i:s'),
            updatedAt: $model->updated_at->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuildMember>  $models
     * @return array<GuildMemberDto>
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
