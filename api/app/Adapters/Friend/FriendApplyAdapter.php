<?php

namespace App\Adapters\Friend;

use App\Models\Sys\SysFriendApply;
use NexusFriend\DataTransferObjects\FriendApply;

/**
 * FriendApplyAdapter
 *
 * SysFriendApply Model と FriendApply の変換を行うアダプター
 */
class FriendApplyAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(SysFriendApply $sysFriendApply): FriendApply
    {
        return new FriendApply(
            id: $sysFriendApply->getId(),
            senderPlayerId: $sysFriendApply->getSenderSysPlayerId(),
            receiverPlayerId: $sysFriendApply->getReceiverSysPlayerId(),
            status: $sysFriendApply->getStatus(),
            createdAt: $sysFriendApply->getCreatedAt(),
            updatedAt: $sysFriendApply->getUpdatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<SysFriendApply>  $sysFriendApplys
     * @return array<FriendApply>
     */
    public static function toDtoArray(iterable $sysFriendApplys): array
    {
        $dtos = [];
        foreach ($sysFriendApplys as $sysFriendApply) {
            $dtos[] = self::toDto($sysFriendApply);
        }

        return $dtos;
    }
}
