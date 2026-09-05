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
    public static function toDto(SysPlayer $model): Player
    {
        return new Player(
            id: $model->getId(),
            uuid: $model->getUuid(),
            myId: $model->getMyId(),
            name: $model->getName(),
            level: $model->getLevel(),
            levelExp: $model->getLevelExp(),
            createdAt: $model->getCreatedAt(),
            updatedAt: (string) $model->getAttribute('updated_at')
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysPlayer>  $models
     * @return array<Player>
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
