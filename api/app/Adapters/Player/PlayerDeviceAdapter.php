<?php

namespace App\Adapters\Player;

use App\Models\Sys\SysPlayerDevice;
use NexusPlayer\DataTransferObjects\PlayerDevice;

/**
 * PlayerDeviceAdapter
 *
 * SysPlayerDevice Model と PlayerDevice の変換を行うアダプター
 */
class PlayerDeviceAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysPlayerDevice $model): PlayerDevice
    {
        return new PlayerDevice(
            id: $model->getId(),
            sysPlayerId: $model->getSysPlayerId(),
            uuid: $model->getUuid(),
            deviceInfo: $model->getDeviceInfo(),
            lastLoginAt: $model->getLastLoginAt(),
            createdAt: $model->getCreatedAt(),
            updatedAt: $model->getUpdatedAt()
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysPlayerDevice>  $models
     * @return array<PlayerDevice>
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
