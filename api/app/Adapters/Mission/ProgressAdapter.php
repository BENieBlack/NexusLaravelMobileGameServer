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
    public static function toDto(TrxProgress $model): Progress
    {
        return new Progress(
            sysPlayerId: $model->getSysPlayerId(),
            triggerType: $model->getTriggerType(),
            subTargetId: $model->getSubTargetId(),
            progressValue: $model->getProgressValue(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxProgress>  $models
     * @return array<int, Progress>
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
