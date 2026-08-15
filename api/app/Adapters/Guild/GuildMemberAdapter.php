<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuildMember;
use Nexus\Core\Utilities\ClockUtility;
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
    public static function toDto(SysGuildMember $model): GuildMember
    {
        return new GuildMember(
            id: $model->getId(),
            sysGuildId: $model->getSysGuildId(),
            sysPlayerId: $model->getSysPlayerId(),
            role: $model->getRole(),
            joinedAt: $model->getJoinedAt(),
            createdAt: ClockUtility::parse((string) $model->created_at)->format('Y-m-d H:i:s'),
            updatedAt: ClockUtility::parse((string) $model->updated_at)->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuildMember>  $models
     * @return array<GuildMember>
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
