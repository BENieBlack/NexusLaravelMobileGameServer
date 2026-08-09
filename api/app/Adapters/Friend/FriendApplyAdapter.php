<?php

namespace App\Adapters\Friend;

use App\Models\Sys\SysFriendApply;
use NexusFriend\Dto\FriendApplyDto;

/**
 * FriendApplyAdapter
 *
 * SysFriendApply Model と FriendApplyDto の変換を行うアダプター
 */
class FriendApplyAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysFriendApply $model): FriendApplyDto
    {
        return new FriendApplyDto(
            id: $model->getId(),
            senderPlayerId: $model->getSenderSysPlayerId(),
            receiverPlayerId: $model->getReceiverSysPlayerId(),
            status: $model->getStatus(),
            createdAt: $model->getCreatedAt(),
            updatedAt: $model->getUpdatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysFriendApply>  $models
     * @return array<FriendApplyDto>
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
