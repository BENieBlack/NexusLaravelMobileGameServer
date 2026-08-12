<?php

namespace App\Adapters\Guild;

use App\Models\Sys\SysGuildApply;
use Nexus\Core\Utilities\ClockUtility;
use NexusGuild\Dto\GuildApplyDto;

/**
 * GuildApplyAdapter
 *
 * SysGuildApply Model と GuildApplyDto の変換を行うアダプター
 */
class GuildApplyAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysGuildApply $model): GuildApplyDto
    {
        return new GuildApplyDto(
            id: $model->getId(),
            sysGuildId: $model->getSysGuildId(),
            sysPlayerId: $model->getSysPlayerId(),
            status: $model->getStatus(),
            createdAt: ClockUtility::parse((string) $model->created_at)->format('Y-m-d H:i:s'),
            updatedAt: ClockUtility::parse((string) $model->updated_at)->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysGuildApply>  $models
     * @return array<GuildApplyDto>
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
