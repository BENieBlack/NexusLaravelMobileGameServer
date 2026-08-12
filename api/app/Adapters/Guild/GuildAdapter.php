<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuild;
use Nexus\Core\Utilities\ClockUtility;
use NexusGuild\Dto\GuildDto;

/**
 * GuildAdapter
 *
 * SysGuild Model と GuildDto の変換を行うアダプター
 */
class GuildAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuild $model): GuildDto
    {
        return new GuildDto(
            id: $model->getId(),
            name: $model->getName(),
            description: $model->getDescription() ?? '',
            level: $model->getLevel(),
            exp: $model->getExp(),
            maxMembers: $model->getMaxMembers(),
            currentMembers: $model->getCurrentMemberCount(),
            createdAt: ClockUtility::parse((string) $model->created_at)->format('Y-m-d H:i:s'),
            updatedAt: ClockUtility::parse((string) $model->updated_at)->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuild>  $models
     * @return array<GuildDto>
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
