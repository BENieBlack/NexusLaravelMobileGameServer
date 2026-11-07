<?php

namespace App\Adapters\Mission;

use App\Models\Trx\TrxProgress;
use NexusMission\DataTransferObjects\Progress;

/**
 * ProgressAdapter
 *
 * TrxProgress Model と Progress の変換を行うアダプター
 */
class ProgressAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxProgress $trxProgress): Progress
    {
        return new Progress(
            sysPlayerId: $trxProgress->getSysPlayerId(),
            triggerType: $trxProgress->getTriggerType(),
            subTargetId: $trxProgress->getSubTargetId(),
            progressValue: $trxProgress->getProgressValue(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxProgress>  $trxProgresss
     * @return array<int, Progress>
     */
    public static function toDtoArray(iterable $trxProgresss): array
    {
        $dtos = [];

        foreach ($trxProgresss as $trxProgress) {
            $dtos[] = self::toDto($trxProgress);
        }

        return $dtos;
    }
}
